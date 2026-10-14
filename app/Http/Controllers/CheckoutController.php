<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\IdempotencyKey;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\SellerOrder;
use App\Services\AuditService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(private readonly WalletService $wallets, private readonly AuditService $audit) {}

    public function store(Request $request)
    {
        abort_unless($request->user()->isBuyer(), 403);
        $data = $request->validate([
            'payment_method' => ['required', 'in:cod'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);
        $buyer = $request->user();
        $cart = $buyer->cart;
        if (! $cart || ! $cart->items()->exists()) {
            return redirect()->route('cart')->withErrors(['checkout' => 'Your bag is empty. Add an item before checking out.']);
        }

        $address = $buyer->addresses()->where('is_default', true)->first() ?? $buyer->addresses()->first();
        if (! $this->hasCompleteAddress($address)) {
            return redirect()->route('buyer.address.create')->withErrors(['address' => 'Add a complete delivery address before placing a COD order.']);
        }

        $idempotencyValue = $request->header('Idempotency-Key', $data['idempotency_key'] ?? '');

        try {
            $order = DB::transaction(function () use ($cart, $buyer, $data, $address, $idempotencyValue) {
            $requestHash = hash('sha256', json_encode([
                'buyer' => $buyer->id,
                'cart' => $cart->id,
                'payment_method' => 'cod',
            ]));

            if ($idempotencyValue !== '') {
                $storedKey = IdempotencyKey::where('scope', 'order.create')->where('key', $idempotencyValue)->lockForUpdate()->first();
                if ($storedKey) {
                    if (! hash_equals($storedKey->request_hash, $requestHash)) {
                        throw ValidationException::withMessages(['checkout' => 'This checkout request was already used for a different bag. Refresh the page and try again.']);
                    }
                    if ($storedKey->order) {
                        return $storedKey->order;
                    }
                } else {
                    $storedKey = IdempotencyKey::create([
                        'scope' => 'order.create',
                        'key' => $idempotencyValue,
                        'request_hash' => $requestHash,
                        'expires_at' => now()->addDay(),
                    ]);
                }
            }

            $items = CartItem::where('cart_id', $cart->id)->where('selected', true)->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['checkout' => 'Select an item before checking out.']);
            }
            $variants = ProductVariant::with('product.seller')->whereIn('id', $items->pluck('product_variant_id'))->lockForUpdate()->get()->keyBy('id');
            $total = 0;

            foreach ($items as $item) {
                $variant = $variants->get($item->product_variant_id);
                if (! $variant || ! $variant->is_active || ! $variant->product->is_active || ! $variant->product->seller->isApproved()) {
                    throw ValidationException::withMessages(['checkout' => 'An item in your bag is no longer available. Remove it or choose another item.']);
                }
                if ($variant->stock < $item->quantity) {
                    throw ValidationException::withMessages(['checkout' => "{$variant->product->name} no longer has enough stock. Update your bag and try again."]);
                }
                $total += $variant->price_minor * $item->quantity;
            }

            $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'LM-'.strtoupper(Str::random(10)), 'total_minor' => $total, 'payment_method' => $data['payment_method'], 'payment_status' => 'pending', 'shipping_address' => $address->only(['label', 'recipient', 'phone', 'line1', 'barangay', 'city', 'province', 'postal_code'])]);
            Payment::create(['order_id' => $order->id, 'method' => $data['payment_method'], 'amount_minor' => $total, 'status' => 'pending']);
            OrderTimeline::create(['order_id' => $order->id, 'actor_id' => $buyer->id, 'status' => 'pending', 'event' => 'order_created', 'note' => 'COD order created.']);

            $items->groupBy(fn (CartItem $item) => $variants->get($item->product_variant_id)->product->seller_id)->each(function ($sellerItems, $sellerId) use ($order, $variants, $buyer) {
                $subtotal = $sellerItems->sum(fn (CartItem $item) => $variants->get($item->product_variant_id)->price_minor * $item->quantity);
                $seller = $variants->get($sellerItems->first()->product_variant_id)->product->seller;
                $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $sellerId, 'subtotal_minor' => $subtotal, 'commission_minor' => (int) round($subtotal * $seller->commission_bps / 10000), 'status' => 'pending']);
                OrderTimeline::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'actor_id' => $buyer->id, 'status' => 'pending', 'event' => 'seller_order_created', 'note' => 'Awaiting seller confirmation.']);
                foreach ($sellerItems as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    $sellerOrder->items()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'variant_name' => $variant->name, 'unit_price_minor' => $variant->price_minor, 'quantity' => $item->quantity]);
                    $stockAfter = $variant->stock - $item->quantity;
                    $variant->update(['stock' => $stockAfter]);
                    InventoryMovement::create(['product_variant_id' => $variant->id, 'seller_order_id' => $sellerOrder->id, 'actor_id' => $buyer->id, 'type' => 'sale_reserved', 'quantity_delta' => -$item->quantity, 'stock_after' => $stockAfter, 'reference' => $order->reference]);
                }
                $this->wallets->recordPendingSale($sellerOrder);
            });

            $cart->items()->delete();
            if (isset($storedKey)) {
                $storedKey->update(['order_id' => $order->id]);
            }
            return $order;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('cart')->withErrors(['checkout' => 'We could not place your COD order right now. Your bag was not changed. Please try again.']);
        }

        $this->audit->record($request, 'order.created', $order, null, ['reference' => $order->reference, 'payment_method' => 'cod']);
        return redirect()->route('orders')->with('status', "Order {$order->reference} placed successfully.");
    }

    private function hasCompleteAddress(?\App\Models\Address $address): bool
    {
        if (! $address) {
            return false;
        }

        foreach (['recipient', 'phone', 'line1', 'barangay', 'city', 'province', 'postal_code'] as $field) {
            if (blank($address->{$field})) {
                return false;
            }
        }

        return true;
    }

    public function orders(Request $request)
    {
        abort_unless($request->user()->isBuyer(), 403);
        $orders = $request->user()->orders()->with(['sellerOrders.seller', 'sellerOrders.items.review'])->latest()->get();
        return view('orders.index', compact('orders'));
    }
}
