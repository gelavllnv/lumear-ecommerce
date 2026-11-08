<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\OrderTimeline;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Notifications\MarketplaceAlert;
use App\Services\AuditService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerOrderController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly WalletService $wallets,
    ) {}

    /**
     * Buyers may cancel only before a seller packs the order. This rule is enforced
     * while the order row is locked so a concurrent seller packing action cannot
     * result in a cancellation after fulfilment has begun.
     */
    public function cancel(Request $request, SellerOrder $sellerOrder)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:300'],
        ]);
        $buyer = $request->user();

        DB::transaction(function () use ($sellerOrder, $buyer, $data): void {
            $lockedOrder = SellerOrder::with(['order', 'items'])
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);
            abort_unless($lockedOrder->order->buyer_id === $buyer->id, 403);

            if (! in_array($lockedOrder->status, ['pending', 'accepted'], true)) {
                throw ValidationException::withMessages([
                    'cancel' => 'This order can no longer be cancelled because the seller has already packed it or fulfilment has started.',
                ]);
            }

            $variants = ProductVariant::whereIn(
                'id',
                $lockedOrder->items->pluck('product_variant_id')->filter()
            )->lockForUpdate()->get()->keyBy('id');

            foreach ($lockedOrder->items as $item) {
                $variant = $variants->get($item->product_variant_id);
                if (! $variant) {
                    continue;
                }

                $stockAfter = $variant->stock + $item->quantity;
                $variant->update(['stock' => $stockAfter]);
                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'seller_order_id' => $lockedOrder->id,
                    'actor_id' => $buyer->id,
                    'type' => 'buyer_order_cancelled_return',
                    'quantity_delta' => $item->quantity,
                    'stock_after' => $stockAfter,
                    'reference' => $lockedOrder->order->reference,
                    'note' => $data['reason'],
                ]);
            }

            $this->wallets->reversePendingSaleForCancellation($lockedOrder, $buyer->id);
            $lockedOrder->update(['status' => 'cancelled']);
            OrderTimeline::create([
                'order_id' => $lockedOrder->order_id,
                'seller_order_id' => $lockedOrder->id,
                'actor_id' => $buyer->id,
                'status' => 'cancelled',
                'event' => 'buyer_order_cancelled',
                'note' => 'Buyer cancelled before packing. Reason: '.$data['reason'],
            ]);
        });

        $sellerOrder->refresh()->loadMissing('order.buyer', 'seller.user');
        $sellerOrder->seller?->user?->notify(new MarketplaceAlert(
            'Order cancelled by buyer',
            "{$sellerOrder->order->reference} was cancelled before packing. Reason: {$data['reason']}",
            route('seller.orders')
        ));
        $this->audit->record(
            $request,
            'order.buyer_cancelled',
            $sellerOrder,
            ['status' => 'pending_or_accepted'],
            ['status' => 'cancelled', 'reason' => $data['reason']]
        );

        return $request->expectsJson()
            ? response()->json(['success' => true, 'status' => 'cancelled'])
            : redirect()->route('orders')->with('status', 'Order cancelled and stock restored.');
    }

    /**
     * Confirms delivery of a COD parcel. It never transfers funds: an administrator
     * must still reconcile the courier remittance before seller earnings are released.
     */
    public function confirmReceipt(Request $request, SellerOrder $sellerOrder)
    {
        $buyer = $request->user();

        DB::transaction(function () use ($sellerOrder, $buyer): void {
            $lockedOrder = SellerOrder::with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            abort_unless($lockedOrder->order->buyer_id === $buyer->id, 403);

            if ($lockedOrder->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'receipt' => 'You can confirm receipt only after the seller has marked this parcel as delivered.',
                ]);
            }

            $lockedOrder->update(['status' => 'completed']);
            OrderTimeline::create([
                'order_id' => $lockedOrder->order_id,
                'seller_order_id' => $lockedOrder->id,
                'actor_id' => $buyer->id,
                'status' => 'completed',
                'event' => 'buyer_receipt_confirmed',
                'note' => 'Buyer confirmed receipt of the COD parcel. Courier remittance still requires administrator reconciliation.',
            ]);
        });

        $this->audit->record($request, 'order.buyer_receipt_confirmed', $sellerOrder, ['status' => 'delivered'], ['status' => 'completed']);

        return $request->expectsJson() ? response()->json(['success' => true]) : redirect()->route('orders')->with('status', 'Receipt confirmed. You can now rate the product. Seller earnings remain subject to courier COD remittance reconciliation.');
    }

    public function storeReview(Request $request, OrderItem $orderItem)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);
        $buyer = $request->user();

        DB::transaction(function () use ($orderItem, $buyer, $data): void {
            $item = OrderItem::with('sellerOrder.order')->lockForUpdate()->findOrFail($orderItem->id);
            abort_unless($item->sellerOrder->order->buyer_id === $buyer->id, 403);

            if ($item->sellerOrder->status !== 'completed') {
                throw ValidationException::withMessages([
                    'rating' => 'You can rate this product after confirming that you received the order.',
                ]);
            }

            ProductReview::updateOrCreate(
                ['buyer_id' => $buyer->id, 'order_item_id' => $item->id],
                [
                    'seller_order_id' => $item->seller_order_id,
                    'product_id' => $item->product_id,
                    'rating' => $data['rating'],
                    'body' => $data['body'] ?? null,
                ],
            );
        });

        $this->audit->record($request, 'product.reviewed', $orderItem, null, ['rating' => $data['rating']]);

        return redirect()->route('orders')->with('status', 'Thank you — your product rating has been saved.');
    }

    public function requestReturn(Request $request, OrderItem $orderItem)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $buyer = $request->user();

        DB::transaction(function () use ($orderItem, $buyer, $data): void {
            $item = OrderItem::with('sellerOrder.order')
                ->lockForUpdate()
                ->findOrFail($orderItem->id);
            abort_unless($item->sellerOrder->order->buyer_id === $buyer->id, 403);

            if (! in_array($item->sellerOrder->status, ['delivered', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'return' => 'You can request a return after the order has been delivered.',
                ]);
            }

            $hasOpenRequest = ReturnRequest::query()
                ->where('order_item_id', $item->id)
                ->whereIn('status', ['requested', 'approved', 'in_dispute', 'refunded'])
                ->exists();
            if ($hasOpenRequest) {
                throw ValidationException::withMessages([
                    'return' => 'A return request already exists for this item.',
                ]);
            }

            ReturnRequest::create([
                'seller_order_id' => $item->seller_order_id,
                'order_item_id' => $item->id,
                'buyer_id' => $buyer->id,
                'reason' => $data['reason'],
                'description' => $data['description'],
                'status' => 'requested',
                'pickup_status' => 'not_started',
                'respond_by' => now()->addDays(2),
            ]);
        });

        $this->audit->record($request, 'order.return_requested', $orderItem, null, ['reason' => $data['reason']]);

        return redirect()->route('orders')->with('status', 'Return request sent to the seller for review.');
    }
}
