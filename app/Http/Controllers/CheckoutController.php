<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Notifications\MarketplaceAlert;
use App\Models\BuyerSellerVoucher;
use App\Models\FlashSale;
use App\Models\IdempotencyKey;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\SellerOrder;
use App\Services\AuditService;
use App\Services\BuyerPromotionService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(private readonly WalletService $wallets, private readonly AuditService $audit, private readonly BuyerPromotionService $promotions) {}

    public function store(Request $request)
    {
        abort_unless($request->user()->isBuyer(), 403);
        $data = $request->validate([
            'payment_method' => ['required', 'in:cod'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'voucher_code' => ['nullable', 'regex:/^[A-Z0-9]{4,12}$/'],
            'address_id' => ['nullable', 'integer'],
        ]);
        $buyer = $request->user();
        $idempotencyValue = $request->header('Idempotency-Key', $data['idempotency_key'] ?? '');
        if ($request->expectsJson() && $idempotencyValue !== '') {
            $previous = IdempotencyKey::where('scope', 'order.create')->where('key', $idempotencyValue)->first();
            if ($previous?->order) {
                abort_unless($previous->order->buyer_id === $buyer->id, 409, 'This checkout key has already been used.');
                return response()->json(['orders' => [['id' => (string) $previous->order_id, 'reference' => $previous->order->reference]]]);
            }
        }
        $cart = $buyer->cart;
        if (! $cart || ! $cart->items()->exists()) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['checkout' => 'Your bag is empty. Refresh your orders before trying again.']);
            }
            return redirect()->route('cart')->withErrors(['checkout' => 'Your bag is empty. Add an item before checking out.']);
        }

        $address = isset($data['address_id']) ? $buyer->addresses()->findOrFail($data['address_id']) : ($buyer->addresses()->where('is_default', true)->first() ?? $buyer->addresses()->first());
        if (! $this->hasCompleteAddress($address)) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['address' => 'Add a complete delivery address before checkout.']);
            }
            return redirect()->route('buyer.address.create')->withErrors(['address' => 'Add a complete delivery address before placing a COD order.']);
        }

        $idempotencyValue = $request->header('Idempotency-Key', $data['idempotency_key'] ?? '');

        try {
            $order = DB::transaction(function () use ($cart, $buyer, $data, $address, $idempotencyValue, $request) {
            $requestHash = hash('sha256', json_encode([
                'buyer' => $buyer->id,
                'cart' => $cart->id,
                'payment_method' => 'cod',
                'voucher_code' => $data['voucher_code'] ?? null,
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
            $pricedItems = collect();
            $flashReservations = [];

            foreach ($items as $item) {
                $variant = $variants->get($item->product_variant_id);
                if (! $variant || ! $variant->is_active || ! $variant->product->is_active || ! $variant->product->seller->isApproved()) {
                    throw ValidationException::withMessages(['checkout' => 'An item in your bag is no longer available. Remove it or choose another item.']);
                }
                if ($variant->product->seller->is_on_vacation) {
                    throw ValidationException::withMessages(['checkout' => "{$variant->product->seller->name} is on vacation. Remove its item before placing your order."]);
                }
                if ($variant->stock < $item->quantity) {
                    throw ValidationException::withMessages(['checkout' => "{$variant->product->name} no longer has enough stock. Update your bag and try again."]);
                }
                $promotion = $this->promotions->priceFor($variant->product, $variant, true);
                $pricedItems->put($item->id, [
                    'unit_price_minor' => $promotion['price_minor'],
                    'line_total_minor' => $promotion['price_minor'] * $item->quantity,
                    'promotion_discount_minor' => 0,
                ]);

                if ($promotion['flash_sale_id']) {
                    $flashReservations[$promotion['flash_sale_id']] = ($flashReservations[$promotion['flash_sale_id']] ?? 0) + $item->quantity;
                }
            }

            $bundlePricing = $this->promotions->bundleDiscountsForCart($items, $pricedItems, true);
            foreach ($items as $item) {
                $line = $pricedItems[$item->id];
                $line['promotion_discount_minor'] = $bundlePricing['discounts']->get($item->id, 0);
                $pricedItems->put($item->id, $line);
            }

            $voucherCode = $data['voucher_code'] ?? null;
            if ($voucherCode) {
                $voucher = \App\Models\SellerVoucher::with('products:id')->where('code', $voucherCode)->lockForUpdate()->first();
                $claim = $voucher
                    ? BuyerSellerVoucher::where('buyer_id', $buyer->id)->where('seller_voucher_id', $voucher->id)->whereNull('removed_at')->lockForUpdate()->first()
                    : null;

                if (! $voucher || ! $claim || ! $voucher->isActive() || $claim->use_count >= $voucher->per_buyer_limit) {
                    throw ValidationException::withMessages(['voucher_code' => 'This voucher is unavailable, expired, or has already been used.']);
                }

                $eligibleItems = $this->promotions->eligibleCartItems($voucher, $items);
                $eligibleSubtotal = $eligibleItems->sum(fn (CartItem $item) => $pricedItems[$item->id]['line_total_minor'] - $pricedItems[$item->id]['promotion_discount_minor']);
                $voucherDiscount = $this->promotions->voucherDiscount($voucher, $eligibleSubtotal);

                if ($voucherDiscount <= 0) {
                    throw ValidationException::withMessages(['voucher_code' => "This voucher needs a minimum eligible spend of ₱".number_format($voucher->minimum_spend_minor / 100, 2).'.']);
                }

                $remainingDiscount = $voucherDiscount;
                foreach ($eligibleItems->values() as $index => $item) {
                    $line = $pricedItems[$item->id];
                    $discountableLineTotal = $line['line_total_minor'] - $line['promotion_discount_minor'];
                    $lineDiscount = $index === $eligibleItems->count() - 1
                        ? $remainingDiscount
                        : min($discountableLineTotal, (int) floor($voucherDiscount * $discountableLineTotal / $eligibleSubtotal));
                    $line['promotion_discount_minor'] += $lineDiscount;
                    $pricedItems->put($item->id, $line);
                    $remainingDiscount -= $lineDiscount;
                }

                $voucher->increment('used_count');
                $claim->increment('use_count');
            }

            foreach ($flashReservations as $flashSaleId => $quantity) {
                $flashSale = FlashSale::query()->lockForUpdate()->findOrFail($flashSaleId);
                if (! $flashSale->isActive() || $flashSale->remainingQuantity() < $quantity) {
                    throw ValidationException::withMessages(['checkout' => 'A flash deal in your bag has just sold out. Refresh your bag and try again.']);
                }
                $flashSale->increment('quantity_sold', $quantity);
            }

            $shipping = $items->groupBy(fn (CartItem $item) => $variants->get($item->product_variant_id)->product->seller_id)->count() * 5000;
            $total = $pricedItems->sum(fn (array $line) => $line['line_total_minor'] - $line['promotion_discount_minor']) + $shipping;
            if ($request->has('expected_quote.total') && $request->input('expected_quote.total') !== $total) {
                throw ValidationException::withMessages(['checkout' => 'Your total changed. Review the updated quote before placing your order.']);
            }

            $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'LM-'.strtoupper(Str::random(10)), 'total_minor' => $total, 'payment_method' => $data['payment_method'], 'payment_status' => 'pending', 'shipping_address' => $address->only(['label', 'recipient', 'phone', 'line1', 'barangay', 'city', 'province', 'postal_code'])]);
            Payment::create(['order_id' => $order->id, 'method' => $data['payment_method'], 'amount_minor' => $total, 'status' => 'pending']);
            OrderTimeline::create(['order_id' => $order->id, 'actor_id' => $buyer->id, 'status' => 'pending', 'event' => 'order_created', 'note' => 'COD order created.']);

            $items->groupBy(fn (CartItem $item) => $variants->get($item->product_variant_id)->product->seller_id)->each(function ($sellerItems, $sellerId) use ($order, $variants, $buyer, $pricedItems) {
                $subtotal = $sellerItems->sum(fn (CartItem $item) => $pricedItems[$item->id]['line_total_minor'] - $pricedItems[$item->id]['promotion_discount_minor']);
                $seller = $variants->get($sellerItems->first()->product_variant_id)->product->seller;
                $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $sellerId, 'subtotal_minor' => $subtotal, 'shipping_fee_minor' => 5000, 'commission_minor' => (int) round($subtotal * $seller->commission_bps / 10000), 'status' => 'pending']);
                OrderTimeline::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'actor_id' => $buyer->id, 'status' => 'pending', 'event' => 'seller_order_created', 'note' => 'Awaiting seller confirmation.']);
                foreach ($sellerItems as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    $sellerOrder->items()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'variant_name' => $variant->name, 'unit_price_minor' => $pricedItems[$item->id]['unit_price_minor'], 'quantity' => $item->quantity, 'promotion_discount_minor' => $pricedItems[$item->id]['promotion_discount_minor']]);
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

            if ($request->expectsJson()) {
                return response()->json(['message' => 'We could not place your order. Refresh your orders before retrying.'], 503);
            }

            return redirect()->route('cart')->withErrors(['checkout' => 'We could not place your COD order right now. Your bag was not changed. Please try again.']);
        }

        $this->audit->record($request, 'order.created', $order, null, ['reference' => $order->reference, 'payment_method' => 'cod']);
        $buyer->notify(new MarketplaceAlert('Order placed', "Your order {$order->reference} is confirmed and awaiting seller action.", route('orders')));
        if ($request->expectsJson()) {
            return response()->json(['orders' => [['id' => (string) $order->id, 'reference' => $order->reference]]], 201);
        }
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
        $orders = $request->user()->orders()->with(['sellerOrders.seller', 'sellerOrders.items.review', 'sellerOrders.returnRequests', 'sellerOrders.shipment.provider', 'sellerOrders.shipment.events', 'timeline'])->latest()->get();
        return view('orders.index', compact('orders'));
    }
}
