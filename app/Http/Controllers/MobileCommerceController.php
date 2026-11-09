<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SavedProduct;
use App\Models\SellerVoucher;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\BuyerPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileCommerceController extends Controller
{
    public function __construct(private readonly BuyerPromotionService $promotions) {}

    private function product(Product $product): array
    {
        $variants = $product->variants->map(fn ($v) => ['id' => (string) $v->id, 'label' => $v->name, 'price' => $this->promotions->priceFor($product, $v)['price_minor'], 'stock' => (int) $v->stock, 'active' => (bool) $v->is_active]);

        return ['id' => (string) $product->id, 'name' => $product->name, 'description' => $product->description ?? '',
            'category' => $product->category?->name ?? '', 'subcategory' => '', 'price' => $variants->min('price') ?? 0,
            'stock' => $variants->sum('stock'), 'store' => $product->seller->name, 'seller' => (string) $product->seller_id,
            'active' => (bool) $product->is_active, 'archived' => ! $product->is_active,
            'variant_label' => $variants->count() === 1 ? $variants->first()['label'] : null,
            'variants' => $variants, 'images' => $product->images->map(fn ($image) => route('media.show', ['path' => $image->path]))->values(),
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
            'paid' => $order->order->payment_status === 'paid', 'payment_status' => ucfirst($order->order->payment_status),
            'can_cancel' => in_array($order->status, ['pending', 'accepted'], true),
            'can_receive' => $order->status === 'delivered', 'can_refund' => in_array($order->status, ['delivered', 'completed'], true),
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
            'profile' => $user->only('name', 'email', 'phone'),
            'categories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'products' => $products->map(fn ($p) => $this->product($p)),
            'cart' => $cart->map(fn ($i) => ['id' => (string) $i->id, 'product_id' => (string) $i->productVariant->product_id, 'variant_id' => (string) $i->product_variant_id, 'name' => $i->productVariant->product->name, 'variant' => $i->productVariant->name, 'price' => $this->promotions->priceFor($i->productVariant->product, $i->productVariant)['price_minor'], 'quantity' => (int) $i->quantity]),
            'orders' => $orders->map(fn ($o) => $this->order($o)), 'addresses' => $user->addresses->map(fn ($a) => $this->address($a)),
            'wishlist' => $user->savedProducts->map(fn ($p) => ['id' => (string) $p->product_id]),
            'notifications' => $user->notifications->map(fn ($n) => ['id' => $n->id, 'title' => $n->data['title'] ?? '', 'body' => $n->data['body'] ?? '', 'read' => $n->read_at !== null]),
            'vouchers' => $this->vouchersFor($user),
            'capabilities' => ['checkout' => true, 'cod' => true, 'chat' => true, 'returns' => true],
        ]);
    }

    public function sellerWorkspace(Request $request): JsonResponse
    {
        $seller = $request->user()->seller;
        $orders = $seller->sellerOrders()->with('order.buyer', 'seller', 'items', 'shipment', 'timeline')->latest()->get();
        $completed = $orders->where('status', 'completed');

        return response()->json(['shop' => ['id' => (string) $seller->id, 'owner' => $request->user()->name, 'status' => $seller->status, 'name' => $seller->name, 'description' => $seller->description ?? '', 'phone' => $request->user()->phone ?? '', 'email' => $request->user()->email],
            'categories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'products' => $seller->products()->with('variants', 'images', 'category', 'seller')->get()->map(fn ($p) => $this->product($p)),
            'orders' => $orders->map(fn ($o) => $this->order($o)),
            'earnings' => ['gross_sales' => $completed->sum('subtotal_minor'), 'fees' => $completed->sum('commission_minor'), 'net_sales' => $completed->sum(fn ($o) => $o->subtotal_minor - $o->commission_minor), 'pending' => $seller->walletAccount?->pending_minor ?? 0, 'available' => $seller->walletAccount?->available_minor ?? 0],
            'capabilities' => ['pickup_confirmation' => true],
            'couriers' => LogisticsProvider::where('status', 'approved')->get(['id', 'name'])->map(fn ($provider) => ['id' => (string) $provider->id, 'name' => $provider->name, 'approved' => true]),
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

    public function addresses(Request $request): JsonResponse
    {
        return response()->json($request->user()->addresses()->orderByDesc('is_default')->latest()->get()->map(fn (Address $address) => $this->address($address)));
    }

    public function destroyAddress(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) {
            $request->user()->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['success' => true]);
    }

    public function updateCart(Request $request, CartItem $item): mixed
    {
        return app(CartController::class)->update($request, $item);
    }

    public function destroyCart(Request $request, CartItem $item): mixed
    {
        return app(CartController::class)->destroy($request, $item);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => (string) $user->id,
            'name' => $user->name,
            'display_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'role' => $user->isSeller() ? 'seller' : 'buyer',
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'regex:/^09[0-9]{9}$/'],
        ]);
        $user->update($data);

        return $this->me($request);
    }

    public function wishlist(Request $request): JsonResponse
    {
        return response()->json($request->user()->savedProducts()->latest()->get()->map(fn (SavedProduct $saved) => ['id' => (string) $saved->product_id]));
    }

    public function saveProduct(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->is_active && $product->seller?->isApproved(), 404);
        SavedProduct::firstOrCreate(['buyer_id' => $request->user()->id, 'product_id' => $product->id]);

        return response()->json(['success' => true]);
    }

    public function removeSavedProduct(Request $request, Product $product): JsonResponse
    {
        SavedProduct::where('buyer_id', $request->user()->id)->where('product_id', $product->id)->delete();

        return response()->json(['success' => true]);
    }

    public function vouchers(Request $request): JsonResponse
    {
        return response()->json($this->vouchersFor($request->user()));
    }

    public function claimVoucher(Request $request, SellerVoucher $voucher): JsonResponse
    {
        DB::transaction(function () use ($request, $voucher): void {
            $voucher = SellerVoucher::lockForUpdate()->findOrFail($voucher->id);
            abort_unless($voucher->is_claimable && $voucher->isActive(), 422, 'This voucher is no longer available to claim.');
            \App\Models\BuyerSellerVoucher::firstOrCreate(
                ['buyer_id' => $request->user()->id, 'seller_voucher_id' => $voucher->id],
                ['claimed_at' => now(), 'use_count' => 0],
            );
        });

        return response()->json(['success' => true]);
    }

    public function sellerSaveProduct(Request $request, ?Product $product = null): JsonResponse
    {
        $seller = $request->user()->seller;
        abort_unless($seller, 403);
        if ($product) {
            abort_unless($product->seller_id === $seller->id, 404);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:5', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'price' => ['required', 'integer', 'min:100', 'max:100000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:99999'],
            'variant_label' => ['required', 'string', 'max:120'],
            'active' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
        ]);
        $product ??= new Product();
        $product->fill([
            'seller_id' => $seller->id,
            'category_id' => $seller->category_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'is_active' => ($data['active'] ?? true) && ! ($data['archived'] ?? false),
            'publication_status' => ($data['archived'] ?? false) ? 'archived' : 'active',
            'moderation_status' => 'active',
            'moderation_reason' => null,
        ]);
        $product->slug ??= Str::slug($data['name']).'-'.Str::lower(Str::random(7));
        $product->save();

        $variant = $product->variants()->first() ?? new ProductVariant();
        $variant->fill([
            'product_id' => $product->id,
            'name' => $data['variant_label'],
            'price_minor' => $data['price'],
            'stock' => $data['stock'],
            'weight_grams' => $variant->weight_grams ?: 100,
            'is_active' => $product->is_active,
        ]);
        $variant->sku ??= 'MOB-'.strtoupper(Str::random(12));
        $variant->save();

        return response()->json($this->product($product->load('variants', 'images', 'category', 'seller')));
    }

    public function archiveSellerProduct(Request $request, Product $product): JsonResponse
    {
        $seller = $request->user()->seller;
        abort_unless($seller && $product->seller_id === $seller->id, 404);
        $archived = $request->boolean('archived');
        $product->update(['is_active' => ! $archived, 'publication_status' => $archived ? 'archived' : 'active']);
        $product->variants()->update(['is_active' => ! $archived]);

        return response()->json(['success' => true]);
    }

    public function updateSellerShop(Request $request): JsonResponse
    {
        $seller = $request->user()->seller;
        abort_unless($seller, 403);
        $data = $request->validate([
            'owner' => ['sometimes', 'string', 'min:2', 'max:255'],
            'name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'phone' => ['sometimes', 'nullable', 'regex:/^09[0-9]{9}$/'],
        ]);
        if (array_key_exists('owner', $data) || array_key_exists('phone', $data)) {
            $request->user()->update(array_filter(['name' => $data['owner'] ?? null, 'phone' => $data['phone'] ?? null], fn ($value) => $value !== null));
        }
        $seller->update(array_filter(['name' => $data['name'] ?? null, 'description' => $data['description'] ?? null], fn ($value) => $value !== null));

        return response()->json(['success' => true]);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function vouchersFor(User $user): \Illuminate\Support\Collection
    {
        return $user->voucherClaims()->with('voucher.seller')->get()->map(fn ($claim) => [
            'id' => (string) $claim->voucher->id,
            'code' => $claim->voucher->code,
            'seller' => $claim->voucher->seller?->name,
            'claimed' => $claim->removed_at === null,
            'used_count' => $claim->use_count,
            'ends_at' => optional($claim->voucher->ends_at)->toIso8601String(),
        ]);
    }

    public function sellerAction(Request $request, SellerOrder $sellerOrder, string $action): mixed
    {
        abort_unless($sellerOrder->seller_id === $request->user()->seller->id, 404);
        if ($action === 'cancel') {
            $request->merge(['status' => 'cancelled']);
            return app(SellerController::class)->updateOrder($request, $sellerOrder);
        }
        abort_unless(in_array($action, ['accept', 'pack', 'prepare', 'ship', 'pickup'], true), 404);
        if ($action === 'accept') {
            abort_unless($sellerOrder->status === 'pending', 422, 'Only pending orders can be accepted.');
            $request->merge(['status' => 'accepted']);
            return app(SellerController::class)->updateOrder($request, $sellerOrder);
        }
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
        if (! in_array($action, ['pack'], true) && $sellerOrder->status === 'packed') {
            $providerId = $request->input('logistics_provider_id', $request->input('courier_id'));
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
