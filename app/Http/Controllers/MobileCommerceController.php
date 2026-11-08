<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Services\BuyerPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileCommerceController extends Controller
{
    public function __construct(private readonly BuyerPromotionService $promotions) {}

    private function product(Product $product): array
    {
        $variants = $product->variants->map(fn ($v) => ['id' => (string) $v->id, 'label' => $v->name, 'price' => $this->promotions->priceFor($product, $v)['price_minor'], 'stock' => (int) $v->stock, 'active' => (bool) $v->is_active]);

        return ['id' => (string) $product->id, 'name' => $product->name, 'description' => $product->description ?? '',
            'category' => $product->category?->name ?? '', 'subcategory' => '', 'price' => $variants->min('price') ?? 0,
            'stock' => $variants->sum('stock'), 'store' => $product->seller->name, 'seller' => (string) $product->seller_id,
            'active' => (bool) $product->is_active, 'variants' => $variants, 'images' => $product->images->map(fn ($image) => route('media.show', ['path' => $image->path]))->values(),
            'reviews' => [], 'rating' => 0, 'review_count' => 0];
    }

    private function address(Address $address): array
    {
        return ['id' => (string) $address->id, 'label' => $address->label, 'name' => $address->recipient, 'phone' => $address->phone,
            'street' => $address->line1, 'barangay' => $address->barangay, 'city' => $address->city, 'province' => $address->province,
            'postal_code' => $address->postal_code, 'default' => (bool) $address->is_default];
    }

    private function order(SellerOrder $order): array
    {
        $address = $order->order->shipping_address;
        $address['name'] = $address['recipient'] ?? '';
        $address['street'] = $address['line1'] ?? '';

        return ['id' => (string) $order->id, 'reference' => $order->order->reference, 'buyer' => $order->order->buyer->name,
            'store' => $order->seller->name, 'raw_status' => $order->status, 'status' => match ($order->status) { 'pending', 'accepted', 'packed', 'ready_to_ship' => 'To Ship', 'shipped' => 'To Receive', default => ucfirst($order->status) },
            'address' => $address, 'payment_method' => 'COD', 'date' => $order->created_at->timestamp,
            'subtotal' => (int) $order->subtotal_minor, 'shipping' => (int) $order->shipping_fee_minor,
            'total' => $order->subtotal_minor + $order->shipping_fee_minor, 'commission' => (int) $order->commission_minor,
            'refund' => 0, 'completed_at' => $order->status === 'completed' ? $order->updated_at->toIso8601String() : null,
            'tracking_code' => $order->shipment?->tracking_code, 'delivery_status' => $order->shipment?->status,
            'items' => $order->items->map(fn ($i) => ['product_id' => (string) $i->product_id, 'name' => $i->product_name, 'price' => (int) $i->unit_price_minor, 'quantity' => (int) $i->quantity, 'variant' => $i->variant_name]),
            'timeline' => $order->timeline->map(fn ($event) => ['status' => $event->status, 'title' => $event->note ?? ucwords(str_replace('_', ' ', $event->event)), 'note' => $event->note, 'date' => $event->created_at->timestamp])];
    }

    public function workspace(Request $request): JsonResponse
    {
        $user = $request->user();
        $products = Product::with('variants', 'images', 'category', 'seller')->where('is_active', true)->whereHas('seller', fn ($q) => $q->where('status', 'approved'))->get();
        $cart = $user->cart?->items()->with('productVariant.product')->get() ?? collect();
        $orders = SellerOrder::with('order.buyer', 'seller', 'items', 'shipment', 'timeline')->whereHas('order', fn ($q) => $q->where('buyer_id', $user->id))->latest()->get();

        return response()->json([
            'profile' => $user->only('name', 'email', 'phone'), 'products' => $products->map(fn ($p) => $this->product($p)),
            'cart' => $cart->map(fn ($i) => ['id' => (string) $i->id, 'product_id' => (string) $i->productVariant->product_id, 'variant_id' => (string) $i->product_variant_id, 'name' => $i->productVariant->product->name, 'variant' => $i->productVariant->name, 'price' => $this->promotions->priceFor($i->productVariant->product, $i->productVariant)['price_minor'], 'quantity' => (int) $i->quantity]),
            'orders' => $orders->map(fn ($o) => $this->order($o)), 'addresses' => $user->addresses->map(fn ($a) => $this->address($a)),
            'wishlist' => $user->savedProducts->map(fn ($p) => ['id' => (string) $p->product_id]),
            'notifications' => $user->notifications->map(fn ($n) => ['id' => $n->id, 'title' => $n->data['title'] ?? '', 'body' => $n->data['body'] ?? '', 'read' => $n->read_at !== null]),
            'vouchers' => [], 'capabilities' => ['checkout' => true, 'cod' => true, 'chat' => false, 'returns' => false],
        ]);
    }

    public function sellerWorkspace(Request $request): JsonResponse
    {
        $seller = $request->user()->seller;
        $orders = $seller->sellerOrders()->with('order.buyer', 'seller', 'items', 'shipment', 'timeline')->latest()->get();
        $completed = $orders->where('status', 'completed');

        return response()->json(['shop' => ['id' => (string) $seller->id, 'owner' => $request->user()->name, 'status' => $seller->status, 'name' => $seller->name, 'description' => $seller->description ?? '', 'phone' => $request->user()->phone ?? '', 'email' => $request->user()->email],
            'products' => $seller->products()->with('variants', 'images', 'category', 'seller')->get()->map(fn ($p) => $this->product($p)),
            'orders' => $orders->map(fn ($o) => $this->order($o)),
            'earnings' => ['gross_sales' => $completed->sum('subtotal_minor'), 'fees' => $completed->sum('commission_minor'), 'net_sales' => $completed->sum(fn ($o) => $o->subtotal_minor - $o->commission_minor), 'pending' => $seller->walletAccount?->pending_minor ?? 0, 'available' => $seller->walletAccount?->available_minor ?? 0],
            'capabilities' => ['pickup_confirmation' => true],
            'logistics_providers' => LogisticsProvider::where('status', 'approved')->get(['id', 'name'])]);
    }

    public function cartAdd(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer']]);

        return app(CartController::class)->add($request, Product::findOrFail($data['product_id']));
    }

    public function quote(Request $request): JsonResponse
    {
        $request->user()->addresses()->findOrFail($request->validate(['address_id' => ['required', 'integer']])['address_id']);
        abort_if($request->input('vouchers', []) !== [], 422, 'Apply vouchers using the website.');
        $items = $request->user()->cart?->items()->where('selected', true)->with('productVariant.product.seller')->get() ?? collect();
        abort_if($items->isEmpty(), 422, 'Your bag is empty.');
        $prices = $items->mapWithKeys(function ($item): array {
            $v = $item->productVariant;
            abort_unless($v && $v->is_active && $v->product->is_active && $v->product->seller->status === 'approved' && ! $v->product->seller->is_on_vacation && $v->stock >= $item->quantity, 422, 'A product in your bag is unavailable.');
            $price = $this->promotions->priceFor($v->product, $v)['price_minor'];

            return [$item->id => ['unit_price_minor' => $price, 'line_total_minor' => $price * $item->quantity, 'promotion_discount_minor' => 0]];
        });
        $bundles = $this->promotions->bundleDiscountsForCart($items, $prices);
        $stores = $items->groupBy(fn ($i) => $i->productVariant->product->seller_id)->map(fn ($group, $sellerId) => [
            'seller_id' => (string) $sellerId, 'store' => $group->first()->productVariant->product->seller->name,
            'subtotal' => $group->sum(fn ($i) => $prices[$i->id]['line_total_minor'] - $bundles['discounts']->get($i->id, 0)),
            'shipping' => 5000,
        ])->values();

        return response()->json(['stores' => $stores, 'total' => $stores->sum(fn ($store) => $store['subtotal'] + $store['shipping'])]);
    }

    public function checkout(Request $request): mixed
    {
        abort_if($request->input('vouchers', []) !== [], 422, 'Apply vouchers using the website.');
        $request->merge(['payment_method' => strtolower($request->string('payment_method')->toString())]);

        return app(CheckoutController::class)->store($request);
    }

    public function saveAddress(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'regex:/^09[0-9]{9}$/'], 'street' => ['required', 'string', 'max:255'], 'barangay' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'], 'province' => ['required', 'string', 'max:255'], 'postal_code' => ['required', 'regex:/^[0-9]{4}$/'], 'default' => ['sometimes', 'boolean']]);
        $address = DB::transaction(function () use ($request, $data, $id): Address {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $address = $id ? $request->user()->addresses()->findOrFail($id) : new Address(['user_id' => $request->user()->id]);
            $default = $data['default'] ?? ! $request->user()->addresses()->exists();
            if ($default) {
                $request->user()->addresses()->update(['is_default' => false]);
            }
            $address->fill(['label' => $data['label'], 'recipient' => $data['name'], 'phone' => $data['phone'], 'line1' => $data['street'], 'barangay' => $data['barangay'], 'city' => $data['city'], 'province' => $data['province'], 'postal_code' => $data['postal_code'], 'is_default' => $default])->save();

            return $address;
        });

        return response()->json($this->address($address));
    }

    public function sellerAction(Request $request, SellerOrder $sellerOrder, string $action): mixed
    {
        abort_unless($sellerOrder->seller_id === $request->user()->seller->id, 404);
        if ($action === 'cancel') {
            $request->merge(['status' => 'cancelled']);
            return app(SellerController::class)->updateOrder($request, $sellerOrder);
        }
        abort_unless(in_array($action, ['pack', 'prepare', 'ship'], true), 404);
        if ($sellerOrder->status === 'pending') {
            $request->merge(['status' => 'accepted']);
            app(SellerController::class)->updateOrder($request, $sellerOrder);
            $sellerOrder->refresh();
        }
        if ($sellerOrder->status === 'accepted') {
            $request->merge(['status' => 'packed']);
            app(SellerController::class)->updateOrder($request, $sellerOrder);
            $sellerOrder->refresh();
        }
        if ($action !== 'pack' && $sellerOrder->status === 'packed') {
            $providerId = $request->input('logistics_provider_id');
            if (! $providerId) {
                $providers = LogisticsProvider::where('status', 'approved')->limit(2)->pluck('id');
                abort_unless($providers->count() === 1, 422, 'Choose a logistics provider on the website before requesting pickup.');
                $providerId = $providers->first();
            }
            $request->merge(['status' => 'ready_to_ship', 'logistics_provider_id' => $providerId]);
            return app(SellerController::class)->updateOrder($request, $sellerOrder);
        }

        return response()->json(['success' => true]);
    }
}
