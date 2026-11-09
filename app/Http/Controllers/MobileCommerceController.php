<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\SavedProduct;
use App\Models\SellerPayoutAccount;
use App\Models\SellerVoucher;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\BuyerPromotionService;
use App\Services\WalletService;
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
            'earnings' => ['gross_sales' => $completed->sum('subtotal_minor'), 'fees' => $completed->sum('commission_minor'), 'net_sales' => $completed->sum(fn ($o) => $o->subtotal_minor - $o->commission_minor), 'pending' => $seller->walletAccount?->pending_minor ?? 0, 'available' => $seller->walletAccount?->available_minor ?? 0, 'available_to_request' => $seller->walletAccount?->available_minor ?? 0,
                'releasable_orders' => [],
                'payout_accounts' => $seller->payoutAccounts()->orderByDesc('is_default')->get()->map(fn (SellerPayoutAccount $account) => ['id' => (string) $account->id, 'name' => $account->account_name, 'type' => $account->type]),],
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

    public function notificationPreferences(Request $request): JsonResponse
    {
        $saved = $request->user()->getAttribute('notification_preferences');
        if (is_string($saved)) {
            $saved = json_decode($saved, true);
        }
        return response()->json(array_merge([
            'order_updates' => true,
            'promotions' => true,
            'general_updates' => true,
        ], is_array($saved) ? $saved : []));
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_updates' => ['required', 'boolean'],
            'promotions' => ['required', 'boolean'],
            'general_updates' => ['required', 'boolean'],
        ]);
        $request->user()->forceFill(['notification_preferences' => json_encode($data)])->save();
        return response()->json($data);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json($request->user()->notifications()->latest()->get()->map(fn ($notice) => [
            'id' => (string) $notice->id,
            'title' => $notice->data['title'] ?? 'Marketplace update',
            'body' => $notice->data['body'] ?? '',
            'read' => $notice->read_at !== null,
        ])->values());
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function markNotificationRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);
        return response()->json(['success' => true]);
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

    /** A JSON version of the seller analytics dashboard used by the Flutter app. */
    public function sellerAnalytics(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after:start'],
        ]);
        $seller = $request->user()->seller;
        abort_unless($seller, 403, 'Seller profile required.');
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['start'])->startOfDay();
        $end = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['end'])->startOfDay();
        $periodDays = $start->diffInDays($end);
        $previousStart = $start->copy()->subDays($periodDays);
        $orders = $seller->sellerOrders()->with(['order.buyer', 'items.product'])->get();

        $metrics = static function ($collection): array {
            $completed = $collection->whereIn('status', ['delivered', 'completed']);
            $revenue = (int) $completed->sum('subtotal_minor');

            return [
                'sales' => $revenue,
                'orders' => $collection->count(),
                'earnings' => (int) $completed->sum(fn (SellerOrder $order) => $order->subtotal_minor - $order->commission_minor),
                'conversion' => 0,
                'units' => (int) $completed->sum(fn (SellerOrder $order) => $order->items->sum('quantity')),
                'aov' => $completed->isEmpty() ? 0 : (int) round($revenue / $completed->count()),
                'cancelled' => $collection->where('status', 'cancelled')->count(),
                'returned' => $collection->whereIn('status', ['returned', 'refunded'])->count(),
            ];
        };
        $currentOrders = $orders->filter(fn (SellerOrder $order) => $order->created_at->gte($start) && $order->created_at->lt($end));
        $previousOrders = $orders->filter(fn (SellerOrder $order) => $order->created_at->gte($previousStart) && $order->created_at->lt($start));
        $completedCurrent = $currentOrders->whereIn('status', ['delivered', 'completed']);
        $buyerCounts = $completedCurrent->groupBy(fn (SellerOrder $order) => $order->order->buyer_id);
        $returning = $buyerCounts->filter(fn ($buyerOrders) => $orders->contains(fn (SellerOrder $order) => $order->order->buyer_id === $buyerOrders->first()->order->buyer_id && $order->created_at->lt($start) && in_array($order->status, ['delivered', 'completed'], true)))->count();

        $products = $seller->products()->with('variants')->get()->map(function (Product $product) use ($completedCurrent): array {
            $items = $completedCurrent->flatMap->items->where('product_id', $product->id);
            return [
                'id' => (string) $product->id,
                'name' => $product->name,
                'views' => 0,
                'cart_rate' => 0,
                'purchases' => $items->count(),
                'units' => (int) $items->sum('quantity'),
                'revenue' => (int) $items->sum(fn ($item) => $item->lineTotalMinor()),
                'low_performing' => false,
                'stock' => (int) $product->variants->sum('stock'),
            ];
        })->sortByDesc('units')->values();
        $promotions = collect()
            ->merge($seller->vouchers()->withCount('claims')->get()->map(fn (SellerVoucher $voucher) => [
                'id' => 'voucher-'.$voucher->id, 'name' => $voucher->code, 'kind' => 'voucher',
                'active' => $voucher->isActive(), 'views' => 0, 'claims' => (int) $voucher->claims_count,
                'redemptions' => (int) $voucher->used_count, 'revenue' => 0, 'conversion' => 0,
                'warning' => $voucher->ends_at?->isFuture() ? 'Ends '.$voucher->ends_at->diffForHumans() : 'Expired',
            ]))
            ->merge($seller->flashSales()->get()->map(fn ($sale) => [
                'id' => 'flash-'.$sale->id, 'name' => $sale->name ?? 'Flash sale', 'kind' => 'flash_sale',
                'active' => $sale->isActive(), 'views' => 0, 'claims' => 0,
                'redemptions' => (int) $sale->quantity_sold, 'revenue' => 0, 'conversion' => 0,
                'warning' => $sale->remainingQuantity().' units remaining',
            ]))->values();
        $returns = ReturnRequest::whereHas('sellerOrder', fn ($query) => $query->where('seller_id', $seller->id))->latest()->get();
        $notifications = $request->user()->notifications()->latest()->take(20)->get()->map(fn ($notice) => [
            'id' => (string) $notice->id, 'type' => $notice->type, 'title' => $notice->data['title'] ?? 'Marketplace update', 'read' => $notice->read_at !== null,
        ]);

        return response()->json([
            'traffic_available' => false,
            'notice' => 'Traffic metrics are unavailable until storefront analytics is enabled.',
            'current' => $metrics($currentOrders), 'previous' => $metrics($previousOrders),
            'daily' => collect(range(0, max(0, $periodDays - 1)))->map(function (int $offset) use ($start, $currentOrders): array {
                $day = $start->copy()->addDays($offset);
                return ['date' => $day->toDateString(), 'revenue' => (int) $currentOrders->filter(fn (SellerOrder $order) => $order->created_at->isSameDay($day) && in_array($order->status, ['delivered', 'completed'], true))->sum('subtotal_minor')];
            })->values(),
            'products' => $products,
            'customers' => ['new' => max(0, $buyerCounts->count() - $returning), 'returning' => $returning, 'repeat' => $buyerCounts->filter(fn ($items) => $items->count() > 1)->count(), 'locations' => []],
            'promotions' => $promotions,
            'operations' => ['new' => $orders->where('status', 'pending')->count(), 'packing' => $orders->whereIn('status', ['accepted', 'packed', 'ready_to_ship'])->count(), 'shipped' => $orders->where('status', 'shipped')->count(), 'delivered' => $orders->whereIn('status', ['delivered', 'completed'])->count(), 'delayed' => 0, 'returns' => $returns->whereIn('status', ['requested', 'approved', 'in_dispute'])->count()],
            'returns' => $returns->map(fn (ReturnRequest $return) => ['id' => (string) $return->id, 'order_id' => (string) $return->seller_order_id, 'reason' => $return->reason, 'status' => $return->status, 'amount' => 0]),
            'notifications' => $notifications,
        ]);
    }

    public function updateSellerPromotion(Request $request, string $promotion): JsonResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);
        $seller = $request->user()->seller;
        $active = $request->boolean('active');
        if (preg_match('/^voucher-(\d+)$/', $promotion, $match)) {
            $seller->vouchers()->findOrFail($match[1])->update(['is_paused' => ! $active]);
        } elseif (preg_match('/^flash-(\d+)$/', $promotion, $match)) {
            $seller->flashSales()->findOrFail($match[1])->update(['is_paused' => ! $active]);
        } else {
            abort(404, 'Promotion not found.');
        }
        return response()->json(['success' => true]);
    }

    public function updateSellerNotification(Request $request, string $notification): JsonResponse
    {
        $request->validate(['read' => ['required', 'boolean']]);
        if ($request->boolean('read')) {
            $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);
        }
        return response()->json(['success' => true]);
    }

    private function mobileReturn(ReturnRequest $return): array
    {
        $item = $return->orderItem;
        return [
            'id' => (string) $return->id,
            'reference' => $return->sellerOrder?->order?->reference ?? 'Return #'.$return->id,
            'product' => $item?->product_name ?? 'Order item',
            'buyer' => $return->buyer?->name ?? 'Buyer',
            'reason' => $return->reason,
            'description' => $return->description,
            'status' => $return->status,
            'response' => $return->seller_response ?? '',
            'amount_minor' => $item ? (int) $item->lineTotalMinor() : 0,
        ];
    }

    public function sellerReturns(Request $request): JsonResponse
    {
        $seller = $request->user()->seller;
        abort_unless($seller, 403, 'Seller profile required.');
        $returns = ReturnRequest::with(['buyer', 'orderItem', 'sellerOrder.order'])
            ->whereHas('sellerOrder', fn ($query) => $query->where('seller_id', $seller->id))
            ->latest()->get();
        return response()->json($returns->map(fn (ReturnRequest $return) => $this->mobileReturn($return))->values());
    }

    public function sellerReturnAction(Request $request, ReturnRequest $returnRequest, string $action): JsonResponse
    {
        $seller = $request->user()->seller;
        abort_unless($seller && $returnRequest->sellerOrder()->where('seller_id', $seller->id)->exists(), 404);
        if ($action === 'refund') {
            abort_unless($request->boolean('items_received'), 422, 'Confirm that the returned items have been received first.');
            abort_unless($returnRequest->status === 'approved', 422, 'Only approved returns can be refunded.');
            // Buyer wallet credits are not implemented in the web data model yet;
            // avoid falsely marking a financial refund as completed.
            abort(422, 'Buyer wallet refunds must be processed by an administrator until buyer wallet accounting is enabled.');
        }
        $data = $request->validate(['response' => ['required', 'string', 'min:3', 'max:2000']]);
        abort_unless($returnRequest->status === 'requested', 422, 'This return request has already been resolved.');
        if ($action === 'approve') {
            $returnRequest->update(['status' => 'approved', 'pickup_status' => 'awaiting_assignment', 'seller_response' => trim($data['response']), 'responded_at' => now()]);
            app(WalletService::class)->reverseForReturn($returnRequest->sellerOrder, $request->user()->id);
        } else {
            $returnRequest->update(['status' => 'rejected', 'seller_response' => trim($data['response']), 'responded_at' => now()]);
        }
        return response()->json(['success' => true, 'return' => $this->mobileReturn($returnRequest->fresh(['buyer', 'orderItem', 'sellerOrder.order']))]);
    }

    public function sellerTasks(Request $request): JsonResponse
    {
        $seller = $request->user()->seller;
        abort_unless($seller, 403, 'Seller profile required.');
        $tasks = DB::table('rider_tasks as task')
            ->join('shipments as shipment', 'shipment.id', '=', 'task.shipment_id')
            ->join('seller_orders as seller_order', 'seller_order.id', '=', 'shipment.seller_order_id')
            ->where('seller_order.seller_id', $seller->id)
            ->select(['task.id', 'task.job_type', 'task.status', 'shipment.tracking_code'])
            ->latest('task.id')->get();
        return response()->json(['tasks' => $tasks]);
    }

    public function updateSellerTask(Request $request, int $task): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:seller_confirmed']]);
        $seller = $request->user()->seller;
        $taskId = DB::table('rider_tasks as task')
            ->join('shipments as shipment', 'shipment.id', '=', 'task.shipment_id')
            ->join('seller_orders as seller_order', 'seller_order.id', '=', 'shipment.seller_order_id')
            ->where('seller_order.seller_id', $seller?->id)
            ->where('task.id', $task)
            ->where('task.status', 'collected')
            ->value('task.id');
        abort_unless($taskId, 422, 'Only collected pickup tasks can be confirmed.');
        DB::table('rider_tasks')->where('id', $taskId)->update(['status' => 'seller_confirmed', 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function requestSellerPayout(Request $request): JsonResponse
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'amount' => ['required', 'integer', 'min:100']]);
        $seller = $request->user()->seller;
        abort_unless($seller, 403, 'Seller profile required.');
        $account = $seller->payoutAccounts()->findOrFail($data['account_id']);
        $payout = app(WalletService::class)->requestPayout($seller, $account, $data['amount']);
        return response()->json(['success' => true, 'id' => (string) $payout->id, 'status' => $payout->status]);
    }

    public function buyerOrders(Request $request): JsonResponse
    {
        $orders = SellerOrder::with('order.buyer', 'seller', 'items', 'shipment', 'timeline')
            ->whereHas('order', fn ($query) => $query->where('buyer_id', $request->user()->id))->latest()->get();
        return response()->json($orders->map(fn (SellerOrder $order) => $this->order($order))->values());
    }

    public function buyerWallet(Request $request): JsonResponse
    {
        $returns = ReturnRequest::with(['sellerOrder.order', 'orderItem'])
            ->where('buyer_id', $request->user()->id)->latest()->get();
        return response()->json([
            'balance_minor' => 0,
            'transactions' => [],
            'returns' => $returns->map(fn (ReturnRequest $return) => [
                'id' => (string) $return->id, 'reference' => $return->sellerOrder?->order?->reference ?? 'Return #'.$return->id,
                'product' => $return->orderItem?->product_name ?? 'Order item', 'status' => $return->status,
                'response' => $return->seller_response ?? '', 'amount_minor' => $return->orderItem ? (int) $return->orderItem->lineTotalMinor() : 0,
            ])->values(),
        ]);
    }

    public function createBuyerReturn(Request $request): JsonResponse
    {
        $data = $request->validate(['order_item_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'min:10', 'max:2000']]);
        $return = DB::transaction(function () use ($data, $request): ReturnRequest {
            $item = \App\Models\OrderItem::with('sellerOrder.order')->lockForUpdate()->findOrFail($data['order_item_id']);
            abort_unless($item->sellerOrder->order->buyer_id === $request->user()->id, 403);
            abort_unless(in_array($item->sellerOrder->status, ['delivered', 'completed'], true), 422, 'You can request a return after delivery.');
            abort_if(ReturnRequest::where('order_item_id', $item->id)->whereIn('status', ['requested', 'approved', 'in_dispute', 'refunded'])->exists(), 422, 'A return request already exists for this item.');
            return ReturnRequest::create(['seller_order_id' => $item->seller_order_id, 'order_item_id' => $item->id, 'buyer_id' => $request->user()->id, 'reason' => $data['reason'], 'description' => $data['description'], 'status' => 'requested', 'pickup_status' => 'not_started', 'respond_by' => now()->addDays(2)]);
        });
        return response()->json(['success' => true, 'id' => (string) $return->id], 201);
    }

    public function products(): JsonResponse
    {
        return response()->json(Product::with('variants', 'images', 'category', 'seller')->where('is_active', true)->whereHas('seller', fn ($query) => $query->where('status', 'approved'))->get()->map(fn (Product $product) => $this->product($product))->values());
    }

    public function showProduct(Product $product): JsonResponse
    {
        abort_unless($product->is_active && $product->seller?->isApproved(), 404);
        return response()->json($this->product($product->load('variants', 'images', 'category', 'seller')));
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
        abort_unless(in_array($action, ['accept', 'pack', 'prepare', 'ship', 'pickup', 'settle'], true), 404);
        if ($action === 'settle') {
            abort(422, 'COD earnings are released only after administrator remittance reconciliation.');
        }
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
