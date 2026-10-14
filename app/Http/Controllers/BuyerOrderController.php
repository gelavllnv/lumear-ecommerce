<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\OrderTimeline;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerOrderController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

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

        return redirect()->route('orders')->with('status', 'Receipt confirmed. You can now rate the product. Seller earnings remain subject to courier COD remittance reconciliation.');
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
}
