<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerOnboardingStep;
use App\Models\SellerOrder;
use App\Models\OrderTimeline;
use App\Services\AuditService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SellerController extends Controller
{
    public function __construct(private readonly WalletService $wallets, private readonly AuditService $audit) {}

    private function seller(Request $request): Seller
    {
        abort_unless($request->user()?->isSeller(), 403);
        return $request->user()->seller ?? abort(403, 'Seller profile required.');
    }

    public function status(Request $request) { return view('seller.status', ['seller' => $this->seller($request)]); }

    public function dashboard(Request $request)
    {
        $seller = $this->seller($request);
        $sellerOrders = $seller->sellerOrders();
        $products = $seller->products()->with('variants')->get();
        return view('seller.dashboard', ['seller' => $seller, 'gross' => (clone $sellerOrders)->whereNotIn('status', ['cancelled'])->sum('subtotal_minor'), 'orders' => $sellerOrders->with(['order.buyer', 'items'])->latest()->take(8)->get(), 'lowStock' => $products->filter(fn (Product $product) => $product->stock <= 5), 'productCount' => $products->count()]);
    }

    public function products(Request $request)
    {
        return view('seller.products.index', ['products' => $this->seller($request)->products()->with(['category', 'variants', 'primaryVariant'])->latest()->paginate(20)]);
    }

    public function create(Request $request)
    {
        $seller = $this->seller($request);
        return view('seller.products.form', ['product' => new Product(), 'seller' => $seller->loadMissing('category'), 'variants' => collect([new ProductVariant(['name' => '', 'sku' => '', 'stock' => 0, 'weight_grams' => 0])])]);
    }

    public function store(Request $request)
    {
        $product = $this->saveProduct($request, new Product());
        return redirect()->route('seller.products.index')->with('status', "{$product->name} created.");
    }

    public function edit(Request $request, Product $product)
    {
        $this->owns($request, $product);
        return view('seller.products.form', ['product' => $product->load('variants'), 'seller' => $this->seller($request)->loadMissing('category'), 'variants' => $product->variants]);
    }

    public function update(Request $request, Product $product)
    {
        $this->owns($request, $product);
        $this->saveProduct($request, $product);
        return redirect()->route('seller.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->owns($request, $product);
        $product->delete();
        return back()->with('status', 'Product removed from your storefront.');
    }

    private function saveProduct(Request $request, Product $product): Product
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'], 'variants' => ['required', 'array', 'min:1', 'max:20'], 'variants.*.id' => ['nullable', 'integer'], 'variants.*.name' => ['required', 'string', 'max:255'], 'variants.*.options_text' => ['nullable', 'string', 'max:1000'], 'variants.*.sku' => ['required', 'string', 'max:255', 'distinct'], 'variants.*.price' => ['required', 'numeric', 'min:0.01'], 'variants.*.stock' => ['required', 'integer', 'min:0'], 'variants.*.weight_grams' => ['required', 'integer', 'min:0'], 'variants.*.is_active' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'], 'images.*' => ['nullable', 'image', 'max:4096']]);
        $seller = $this->seller($request);
        if (! $seller->category_id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['category' => 'Choose your one store category in onboarding before adding products.']);
        }
        $moderationReason = $this->prohibitedListingReason($data['name'].' '.$data['description']);
        $this->ensureVariantOwnershipAndSkuAvailability($product, $data['variants']);

        DB::transaction(function () use ($request, $product, $data, $seller, $moderationReason) {
            $product->fill(['seller_id' => $seller->id, 'name' => $data['name'], 'category_id' => $seller->category_id, 'description' => $data['description'], 'is_active' => $moderationReason ? false : $request->boolean('is_active'), 'moderation_status' => $moderationReason ? 'flagged' : 'active', 'moderation_reason' => $moderationReason]);
            $product->slug ??= Str::slug($data['name']).'-'.Str::lower(Str::random(6));
            $product->save();
            foreach ($data['variants'] as $variantData) {
                $variant = isset($variantData['id']) ? ProductVariant::withTrashed()->find($variantData['id']) : new ProductVariant();
                $wasNew = ! $variant->exists;
                $previousStock = $variant->stock ?? 0;
                if ($variant->exists) $variant->restore();
                $variant->fill(['product_id' => $product->id, 'sku' => $variantData['sku'], 'name' => $variantData['name'], 'options' => $this->parseVariantOptions($variantData['options_text'] ?? ''), 'price_minor' => (int) round($variantData['price'] * 100), 'stock' => $variantData['stock'], 'weight_grams' => $variantData['weight_grams'], 'is_active' => (bool) ($variantData['is_active'] ?? true)]);
                $variant->save();
                $delta = $variant->stock - $previousStock;
                if ($wasNew || $delta !== 0) {
                    InventoryMovement::create(['product_variant_id' => $variant->id, 'actor_id' => $request->user()->id, 'type' => $wasNew ? 'initial_stock' : 'manual_adjustment', 'quantity_delta' => $delta, 'stock_after' => $variant->stock, 'reference' => $product->slug]);
                }
            }
            foreach ($request->file('images', []) as $index => $image) $product->images()->create(['path' => $image->store('product-images', 'public'), 'position' => $product->images()->max('position') + $index + 1]);
            SellerOnboardingStep::updateOrCreate(['seller_id' => $product->seller_id, 'step' => 'first_product'], ['completed_at' => now()]);
        });

        if ($moderationReason) {
            $this->audit->record($request, 'product.auto_flagged', $product, null, ['reason' => $moderationReason]);
        }

        return $product;
    }

    private function ensureVariantOwnershipAndSkuAvailability(Product $product, array $variants): void
    {
        $ownedIds = $product->exists ? $product->variants()->withTrashed()->pluck('id')->all() : [];
        foreach ($variants as $index => $variant) {
            if (isset($variant['id']) && ! in_array((int) $variant['id'], $ownedIds, true)) abort(403, 'You can only edit variants from your own product.');
            $taken = ProductVariant::withTrashed()->where('sku', $variant['sku'])->when(isset($variant['id']), fn ($query) => $query->where('id', '!=', $variant['id']))->exists();
            if ($taken) throw \Illuminate\Validation\ValidationException::withMessages(["variants.$index.sku" => 'This SKU is already used by another product variant.']);
        }
    }

    private function parseVariantOptions(string $input): ?array
    {
        $options = collect(preg_split('/[,;]+/', $input))->mapWithKeys(function (string $pair) {
            [$key, $value] = array_pad(explode(':', $pair, 2), 2, null);
            return [trim((string) $key) => trim((string) $value)];
        })->filter(fn ($value, $key) => $key !== '' && $value !== '')->all();

        return $options ?: null;
    }

    private function owns(Request $request, Product $product): void { abort_unless($product->seller_id === $this->seller($request)->id, 403); }

    public function inventory(Request $request) { return view('seller.inventory', ['products' => $this->seller($request)->products()->with('variants')->get()->sortBy('stock')]); }

    public function adjustInventory(Request $request, ProductVariant $variant)
    {
        abort_unless($variant->product->seller_id === $this->seller($request)->id, 403);
        $data = $request->validate(['quantity_delta' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'], 'note' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($variant, $request, $data): void {
            $variant = ProductVariant::lockForUpdate()->findOrFail($variant->id);
            $newStock = $variant->stock + $data['quantity_delta'];
            if ($newStock < 0) throw \Illuminate\Validation\ValidationException::withMessages(['quantity_delta' => 'Stock cannot go below zero.']);
            $variant->update(['stock' => $newStock]);
            InventoryMovement::create(['product_variant_id' => $variant->id, 'actor_id' => $request->user()->id, 'type' => 'manual_adjustment', 'quantity_delta' => $data['quantity_delta'], 'stock_after' => $newStock, 'reference' => 'SELLER-INVENTORY', 'note' => $data['note'] ?? null]);
        });
        return back()->with('status', 'Inventory updated.');
    }

    public function orders(Request $request)
    {
        return view('seller.orders', [
            'sellerOrders' => $this->seller($request)->sellerOrders()->with(['order.buyer', 'items'])->latest()->paginate(30),
            'allowedTransitions' => $this->allowedOrderTransitions(),
        ]);
    }

    public function updateOrder(Request $request, SellerOrder $sellerOrder)
    {
        abort_unless($sellerOrder->seller_id === $this->seller($request)->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'accepted', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'completed', 'cancelled'])]]);

        $allowedTransitions = $this->allowedOrderTransitions();
        if (! in_array($data['status'], $allowedTransitions[$sellerOrder->status] ?? [], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Choose the next available fulfillment action for this order.']);
        }

        DB::transaction(function () use ($request, $sellerOrder, $data, $allowedTransitions): void {
            $sellerOrder = SellerOrder::with('items')->lockForUpdate()->findOrFail($sellerOrder->id);
            $previousStatus = $sellerOrder->status;
            if (! in_array($data['status'], $allowedTransitions[$previousStatus] ?? [], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This order was updated by another action. Refresh the page and try again.']);
            }

            $sellerOrder->update([
                'status' => $data['status'],
                'delivered_at' => $data['status'] === 'delivered' ? now() : $sellerOrder->delivered_at,
            ]);
            OrderTimeline::create(['order_id' => $sellerOrder->order_id, 'seller_order_id' => $sellerOrder->id, 'actor_id' => $request->user()->id, 'status' => $data['status'], 'event' => 'seller_status_updated', 'note' => "Changed from {$previousStatus} to {$data['status']}." ]);

            if ($data['status'] === 'cancelled') {
                $variants = ProductVariant::whereIn('id', $sellerOrder->items->pluck('product_variant_id'))->lockForUpdate()->get()->keyBy('id');
                foreach ($sellerOrder->items as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    if (! $variant) {
                        continue;
                    }
                    $variant->increment('stock', $item->quantity);
                    $variant->refresh();
                    InventoryMovement::create(['product_variant_id' => $variant->id, 'seller_order_id' => $sellerOrder->id, 'actor_id' => $request->user()->id, 'type' => 'order_cancelled_return', 'quantity_delta' => $item->quantity, 'stock_after' => $variant->stock, 'reference' => $sellerOrder->order->reference]);
                }
            }
            if ($data['status'] === 'completed') {
                $this->wallets->releaseCodEarnings($sellerOrder, $request->user()->id, 'SELLER-COMPLETED-'.$sellerOrder->id);
                if ($sellerOrder->order->sellerOrders()->whereNotIn('status', ['completed', 'cancelled'])->doesntExist()) {
                    $sellerOrder->order->update(['payment_status' => 'paid']);
                }
            }
        });

        return redirect()->route('seller.orders')->with('status', 'Fulfillment status updated.');
    }

    /** @return array<string, array<int, string>> */
    private function allowedOrderTransitions(): array
    {
        return [
            'pending' => ['accepted', 'cancelled'],
            'accepted' => ['packed', 'cancelled'],
            'packed' => ['ready_to_ship', 'cancelled'],
            'ready_to_ship' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'cancelled'],
            // Buyers, not sellers, confirm receipt and complete COD orders.
            'delivered' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];
    }

    public function earnings(Request $request)
    {
        $seller = $this->seller($request);
        $gross = $seller->sellerOrders()->whereIn('status', ['delivered', 'completed'])->sum('subtotal_minor');
        return view('seller.earnings', compact('seller', 'gross'));
    }

    public function reviews(Request $request)
    {
        $seller = $this->seller($request);

        return view('seller.reviews', [
            'reviews' => ProductReview::with(['buyer', 'product'])
                ->whereHas('product', fn ($query) => $query->where('seller_id', $seller->id))
                ->latest()
                ->paginate(30),
        ]);
    }
    public function settings(Request $request) { return view('seller.settings', ['seller' => $this->seller($request)]); }

    public function saveSettings(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate(['description' => ['nullable', 'max:3000'], 'phone' => ['nullable', 'max:255'], 'logo' => ['nullable', 'image', 'max:2048'], 'banner' => ['nullable', 'image', 'max:4096']]);
        $seller->fill(['description' => $data['description']]);
        if ($request->file('logo')) $seller->logo_path = $request->file('logo')->store('seller-assets', 'public');
        if ($request->file('banner')) $seller->banner_path = $request->file('banner')->store('seller-assets', 'public');
        $seller->save();
        $seller->user->update(['phone' => $data['phone']]);
        return back()->with('status', 'Shop settings saved.');
    }

    private function prohibitedListingReason(string $listing): ?string
    {
        $normalized = mb_strtolower($listing);
        $terms = ['gun', 'firearm', 'rifle', 'pistol', 'ammunition', 'ammo', 'explosive', 'grenade', 'cocaine', 'heroin', 'methamphetamine', 'shabu', 'illegal drug', 'prohibited substance'];
        foreach ($terms as $term) if (str_contains($normalized, $term)) return 'Potential prohibited item detected: '.ucfirst($term).'. The listing was hidden for administrator review.';
        return null;
    }
}
