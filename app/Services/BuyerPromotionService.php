<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\BundleDeal;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductVariant;
use App\Models\SellerVoucher;
use App\Models\User;
use Illuminate\Support\Collection;

class BuyerPromotionService
{
    /**
     * Resolve the lowest valid product-level promotional price for a variant.
     * The source is returned so checkout can reserve flash-sale inventory safely.
     *
     * @return array{regular_price_minor:int,price_minor:int,label:?string,discount_percent:?int,flash_sale_id:?int,flash_ends_at:?string}
     */
    public function priceFor(Product $product, ProductVariant $variant, bool $lockForCheckout = false): array
    {
        $regularPrice = (int) $variant->price_minor;
        $best = [
            'regular_price_minor' => $regularPrice,
            'price_minor' => $regularPrice,
            'label' => null,
            'discount_percent' => null,
            'flash_sale_id' => null,
            'flash_ends_at' => null,
        ];

        foreach ($this->productSalesFor($product, $lockForCheckout) as $sale) {
            $salePrice = $sale->discount_type === 'percentage'
                ? (int) round($regularPrice * (100 - $sale->discount_percent) / 100)
                : min($regularPrice, (int) $sale->sale_price_minor);

            if ($salePrice < $best['price_minor']) {
                $best = [
                    'regular_price_minor' => $regularPrice,
                    'price_minor' => $salePrice,
                    'label' => $sale->discount_type === 'percentage' ? $sale->discount_percent.'% off' : 'Sale price',
                    'discount_percent' => $sale->discount_type === 'percentage'
                        ? (int) $sale->discount_percent
                        : (int) round((1 - ($salePrice / max(1, $regularPrice))) * 100),
                    'flash_sale_id' => null,
                    'flash_ends_at' => null,
                ];
            }
        }

        foreach ($this->flashSalesFor($product, $lockForCheckout) as $flashSale) {
            $flashPrice = min($regularPrice, (int) $flashSale->flash_price_minor);

            if ($flashPrice < $best['price_minor']) {
                $best = [
                    'regular_price_minor' => $regularPrice,
                    'price_minor' => $flashPrice,
                    'label' => 'Flash deal',
                    'discount_percent' => (int) round((1 - ($flashPrice / max(1, $regularPrice))) * 100),
                    'flash_sale_id' => $flashSale->id,
                    'flash_ends_at' => $flashSale->ends_at->toIso8601String(),
                ];
            }
        }

        return $best;
    }

    /**
     * Flash-deal landing areas must retain the campaign that selected the product.
     * A general product sale must not make the countdown or flash label disappear.
     *
     * @return array{regular_price_minor:int,price_minor:int,label:string,discount_percent:int,flash_sale_id:int,flash_ends_at:string}
     */
    public function priceForFlashSale(Product $product, ProductVariant $variant, FlashSale $flashSale): array
    {
        $regularPrice = (int) $variant->price_minor;
        $flashPrice = min($regularPrice, (int) $flashSale->flash_price_minor);

        return [
            'regular_price_minor' => $regularPrice,
            'price_minor' => $flashPrice,
            'label' => 'Flash deal',
            'discount_percent' => (int) round((1 - ($flashPrice / max(1, $regularPrice))) * 100),
            'flash_sale_id' => $flashSale->id,
            'flash_ends_at' => $flashSale->ends_at->toIso8601String(),
        ];
    }
    /** @param Collection<int, Product> $products */
    public function decorateProducts(Collection $products): void
    {
        $products->each(function (Product $product): void {
            $variant = $product->relationLoaded('primaryVariant')
                ? $product->primaryVariant
                : $product->primaryVariant()->first();

            if ($variant) {
                $product->setAttribute('buyer_promotion', $this->priceFor($product, $variant));
            }
        });
    }

    /** @return Collection<int, SellerVoucher> */
    public function activeVouchersForSeller(int $sellerId): Collection
    {
        return SellerVoucher::query()
            ->with(['products:id', 'seller:id,name,slug'])
            ->where('seller_id', $sellerId)
            ->where('is_claimable', true)->where('is_paused', false)->whereNull('archived_at')
            ->whereIn('status', ['upcoming', 'ongoing'])->where('is_paused', false)->whereNull('archived_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereColumn('used_count', '<', 'total_usage_limit')
            ->orderBy('ends_at')
            ->get();
    }

    /** @return Collection<int, SellerVoucher> */
    public function activeVouchers(int $limit = 8): Collection
    {
        return SellerVoucher::query()
            ->with(['products:id', 'seller:id,name,slug'])
            ->where('is_claimable', true)->where('is_paused', false)->whereNull('archived_at')
            ->whereIn('status', ['upcoming', 'ongoing'])->where('is_paused', false)->whereNull('archived_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereColumn('used_count', '<', 'total_usage_limit')
            ->orderBy('ends_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, FlashSale> */
    public function activeFlashDeals(int $limit = 4): Collection
    {
        return FlashSale::query()
            ->with(['product.seller', 'product.images', 'product.variants', 'product.primaryVariant', 'product.sales', 'product.flashSales'])
            ->whereIn('status', ['upcoming', 'running'])->where('is_paused', false)->whereNull('archived_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereColumn('quantity_sold', '<', 'quantity_limit')
            ->whereHas('product', fn ($query) => $query->where('is_active', true)
                ->whereHas('seller', fn ($seller) => $seller->where('status', 'approved'))
                ->whereHas('variants', fn ($variant) => $variant->where('is_active', true)->where('stock', '>', 0)))
            ->orderBy('ends_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, BundleDeal> */
    public function activeBundles(int $limit = 6): Collection
    {
        return $this->activeBundleQuery()
            ->with(['seller:id,name,slug', 'qualifierProducts:id,name,slug', 'rewardProducts:id,name,slug'])
            ->whereHas('seller', fn ($query) => $query->where('status', 'approved'))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, BundleDeal> */
    public function activeBundlesForProduct(Product $product): Collection
    {
        return $this->activeBundleQuery()
            ->with(['seller:id,name,slug', 'qualifierProducts:id,name,slug', 'rewardProducts:id,name,slug'])
            ->where('seller_id', $product->seller_id)
            ->where(function ($query) use ($product): void {
                $query->whereHas('qualifierProducts', fn ($products) => $products->whereKey($product->id))
                    ->orWhereHas('rewardProducts', fn ($products) => $products->whereKey($product->id));
            })
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @param Collection<int, CartItem> $items
     *  @param Collection<int, array{unit_price_minor:int,line_total_minor:int,promotion_discount_minor:int}> $pricedItems
     *  @return array{discounts: Collection<int, int>, savings: int, applied: Collection<int, array{name:string,detail:string,savings:int}>}
     */
    public function bundleDiscountsForCart(Collection $items, Collection $pricedItems, bool $lockForCheckout = false): array
    {
        $discounts = $items->mapWithKeys(fn (CartItem $item) => [$item->id => 0]);
        $applied = collect();

        if ($items->isEmpty()) {
            return compact('discounts', 'applied') + ['savings' => 0];
        }

        $sellerIds = $items->map(fn (CartItem $item) => $item->productVariant->product->seller_id)->unique()->values();
        $query = $this->activeBundleQuery()->with(['qualifierProducts:id', 'rewardProducts:id'])->whereIn('seller_id', $sellerIds);

        if ($lockForCheckout) {
            $query->lockForUpdate();
        }

        $bundlesBySeller = $query->get()->groupBy('seller_id');

        $items->groupBy(fn (CartItem $item) => $item->productVariant->product->seller_id)->each(function (Collection $sellerItems, int $sellerId) use ($bundlesBySeller, $pricedItems, $discounts, $applied): void {
            $best = null;

            foreach ($bundlesBySeller->get($sellerId, collect()) as $bundle) {
                $candidate = $this->bundleDiscountForItems($bundle, $sellerItems, $pricedItems);

                if ($candidate['savings'] > 0 && (! $best || $candidate['savings'] > $best['savings'])) {
                    $best = $candidate;
                }
            }

            if (! $best) {
                return;
            }

            foreach ($best['discounts'] as $itemId => $discount) {
                $discounts->put($itemId, $discount);
            }

            $applied->push([
                'name' => $best['bundle']->name,
                'detail' => $best['detail'],
                'savings' => $best['savings'],
            ]);
        });

        return ['discounts' => $discounts, 'savings' => $discounts->sum(), 'applied' => $applied];
    }

    /** @param Collection<int, CartItem> $items
     *  @return Collection<int, SellerVoucher>
     */
    public function claimedVouchersForCart(User $buyer, Collection $items): Collection
    {
        $sellerIds = $items->map(fn (CartItem $item) => $item->productVariant->product->seller_id)->unique()->values();

        return SellerVoucher::query()
            ->with(['products:id', 'seller:id,name'])
            ->whereIn('seller_id', $sellerIds)
            ->whereIn('status', ['upcoming', 'ongoing'])->where('is_paused', false)->whereNull('archived_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereColumn('used_count', '<', 'total_usage_limit')
            ->whereHas('claims', fn ($query) => $query->where('buyer_id', $buyer->id)->whereNull('removed_at')->whereColumn('use_count', '<', 'seller_vouchers.per_buyer_limit'))
            ->orderBy('ends_at')
            ->get()
            ->filter(fn (SellerVoucher $voucher) => $this->eligibleCartItems($voucher, $items)->isNotEmpty())
            ->values();
    }

    /** @param Collection<int, CartItem> $items
     *  @return Collection<int, CartItem>
     */
    public function eligibleCartItems(SellerVoucher $voucher, Collection $items): Collection
    {
        $productIds = $voucher->relationLoaded('products')
            ? $voucher->products->pluck('id')
            : $voucher->products()->pluck('products.id');

        return $items->filter(function (CartItem $item) use ($voucher, $productIds): bool {
            $product = $item->productVariant->product;

            return $product->seller_id === $voucher->seller_id
                && ($productIds->isEmpty() || $productIds->contains($product->id));
        })->values();
    }

    public function voucherDiscount(SellerVoucher $voucher, int $eligibleSubtotal): int
    {
        if ($eligibleSubtotal < $voucher->minimum_spend_minor) {
            return 0;
        }

        if ($voucher->discount_type === 'fixed') {
            return min($eligibleSubtotal, (int) $voucher->discount_minor);
        }

        $discount = (int) floor($eligibleSubtotal * ((int) $voucher->discount_percent) / 100);

        return min($eligibleSubtotal, $discount, (int) $voucher->discount_cap_minor);
    }

    /** @param Collection<int, CartItem> $items
     *  @param Collection<int, array{unit_price_minor:int,line_total_minor:int,promotion_discount_minor:int}> $pricedItems
     *  @return array{bundle: BundleDeal, discounts: Collection<int, int>, savings: int, detail: string}
     */
    private function bundleDiscountForItems(BundleDeal $bundle, Collection $items, Collection $pricedItems): array
    {
        $empty = $items->mapWithKeys(fn (CartItem $item) => [$item->id => 0]);
        $qualifierIds = $bundle->qualifierProducts->pluck('id');
        $qualifyingItems = $items->filter(fn (CartItem $item) => $qualifierIds->contains($item->productVariant->product_id))->values();
        $qualifyingQuantity = $qualifyingItems->sum('quantity');

        if ($qualifyingQuantity < $bundle->buy_quantity) {
            return ['bundle' => $bundle, 'discounts' => $empty, 'savings' => 0, 'detail' => 'Add more qualifying products'];
        }

        if ($bundle->type === 'percentage_bundle') {
            $discounts = $empty;
            $discountableQuantity = intdiv($qualifyingQuantity, $bundle->buy_quantity) * $bundle->buy_quantity;

            foreach ($qualifyingItems as $item) {
                if ($discountableQuantity === 0) {
                    break;
                }

                $quantity = min($item->quantity, $discountableQuantity);
                $unitPrice = $pricedItems[$item->id]['unit_price_minor'];
                $discounts->put($item->id, (int) floor($unitPrice * $quantity * $bundle->discount_percent / 100));
                $discountableQuantity -= $quantity;
            }

            return [
                'bundle' => $bundle,
                'discounts' => $discounts,
                'savings' => $discounts->sum(),
                'detail' => $bundle->discount_percent.'% off when buying '.$bundle->buy_quantity.' qualifying items',
            ];
        }

        $rewardIds = $bundle->rewardProducts->pluck('id');
        $rewardItems = $items
            ->filter(fn (CartItem $item) => $rewardIds->contains($item->productVariant->product_id))
            ->sortBy(fn (CartItem $item) => $pricedItems[$item->id]['unit_price_minor'])
            ->values();
        $freeQuantity = intdiv($qualifyingQuantity, $bundle->buy_quantity) * $bundle->get_quantity;
        $discounts = $empty;

        foreach ($rewardItems as $item) {
            if ($freeQuantity === 0) {
                break;
            }

            $quantity = min($item->quantity, $freeQuantity);
            $discounts->put($item->id, $pricedItems[$item->id]['unit_price_minor'] * $quantity);
            $freeQuantity -= $quantity;
        }

        return [
            'bundle' => $bundle,
            'discounts' => $discounts,
            'savings' => $discounts->sum(),
            'detail' => 'Buy '.$bundle->buy_quantity.', get '.$bundle->get_quantity.' reward item'.($bundle->get_quantity === 1 ? '' : 's').' free',
        ];
    }

    private function activeBundleQuery()
    {
        return BundleDeal::query()
            ->where('is_active', true)->whereNull('archived_at')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** @return Collection<int, ProductSale> */
    private function productSalesFor(Product $product, bool $lockForCheckout): Collection
    {
        if (! $lockForCheckout && $product->relationLoaded('sales')) {
            return $product->sales->filter(fn (ProductSale $sale) => $this->saleIsActive($sale))->values();
        }

        $query = $product->sales()
            ->whereIn('status', ['upcoming', 'running'])->where('is_paused', false)->whereNull('archived_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());

        if ($lockForCheckout) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /** @return Collection<int, FlashSale> */
    private function flashSalesFor(Product $product, bool $lockForCheckout): Collection
    {
        if (! $lockForCheckout && $product->relationLoaded('flashSales')) {
            return $product->flashSales->filter(fn (FlashSale $sale) => $this->flashSaleIsActive($sale))->values();
        }

        $query = $product->flashSales()
            ->whereIn('status', ['upcoming', 'running'])->where('is_paused', false)->whereNull('archived_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereColumn('quantity_sold', '<', 'quantity_limit');

        if ($lockForCheckout) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function saleIsActive(ProductSale $sale): bool
    {
        return in_array($sale->status, ['upcoming', 'running'], true)
            && now()->between($sale->starts_at, $sale->ends_at->copy()->subSecond());
    }

    private function flashSaleIsActive(FlashSale $sale): bool
    {
        return in_array($sale->status, ['upcoming', 'running'], true)
            && now()->between($sale->starts_at, $sale->ends_at->copy()->subSecond())
            && $sale->remainingQuantity() > 0;
    }
}
