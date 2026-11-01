<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\BuyerPromotionService;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function __construct(private readonly BuyerPromotionService $promotions) {}

    public function index(Request $request)
    {
        $products = Product::with(['seller', 'category', 'images', 'variants', 'primaryVariant', 'sales', 'flashSales'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('is_active', true)
            ->whereHas('seller', fn ($query) => $query->where('status', 'approved'))
            ->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->trim();
                $query->where(fn ($match) => $match->where('name', 'like', "%{$term}%")->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$term}%")));
            })
            ->latest()->paginate(12)->withQueryString();

        $this->promotions->decorateProducts($products->getCollection());
        $flashProducts = $this->promotions->activeFlashDeals()->map(function ($flashSale) {
            $product = $flashSale->product;
            $product->setAttribute('buyer_promotion', $this->promotions->priceForFlashSale($product, $product->primaryVariant, $flashSale));

            return $product;
        });
        $claimableVouchers = $this->promotions->activeVouchers();
        $activeBundles = $this->promotions->activeBundles();
        $claimedVoucherIds = auth()->user()?->isBuyer()
            ? auth()->user()->voucherClaims()->whereNull('removed_at')->whereIn('seller_voucher_id', $claimableVouchers->pluck('id'))->pluck('seller_voucher_id')
            : collect();

        return view('marketplace.index', [
            'products' => $products,
            'flashProducts' => $flashProducts,
            'categories' => Category::where('is_active', true)->orderBy('position')->get(),
            'claimableVouchers' => $claimableVouchers,
            'activeBundles' => $activeBundles,
            'claimedVoucherIds' => $claimedVoucherIds,
        ]);
    }

    public function deals(): \Illuminate\View\View
    {
        $flashProducts = $this->promotions->activeFlashDeals(12)->map(function ($flashSale) {
            $product = $flashSale->product;
            $product->setAttribute('buyer_promotion', $this->promotions->priceForFlashSale($product, $product->primaryVariant, $flashSale));

            return $product;
        });
        $claimableVouchers = $this->promotions->activeVouchers(12);
        $activeBundles = $this->promotions->activeBundles(12);
        $claimedVoucherIds = auth()->user()?->isBuyer()
            ? auth()->user()->voucherClaims()->whereNull('removed_at')->whereIn('seller_voucher_id', $claimableVouchers->pluck('id'))->pluck('seller_voucher_id')
            : collect();

        return view('marketplace.deals', compact('flashProducts', 'claimableVouchers', 'activeBundles', 'claimedVoucherIds'));
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active && $product->seller->isApproved(), 404);
        $product->loadCount('reviews')->loadAvg('reviews', 'rating')->load(['category', 'seller', 'images', 'sales', 'flashSales', 'variants' => fn ($query) => $query->where('is_active', true)->where('stock', '>', 0)->orderBy('price_minor')]);
        abort_if($product->variants->isEmpty(), 404);
        $product->setRelation('primaryVariant', $product->variants->first());
        $product->setAttribute('buyer_promotion', $this->promotions->priceFor($product, $product->primaryVariant));
        $availableVouchers = $this->promotions->activeVouchersForSeller($product->seller_id);
        $availableBundles = $this->promotions->activeBundlesForProduct($product);
        $claimedVoucherIds = auth()->user()?->isBuyer()
            ? auth()->user()->voucherClaims()->whereNull('removed_at')->whereIn('seller_voucher_id', $availableVouchers->pluck('id'))->pluck('seller_voucher_id')
            : collect();

        return view('marketplace.show', compact('product', 'availableVouchers', 'availableBundles', 'claimedVoucherIds'));
    }

    public function shop(Seller $seller)
    {
        abort_unless($seller->isApproved(), 404);
        $products = $seller->products()->with(['images', 'variants', 'primaryVariant', 'sales', 'flashSales'])->withCount('reviews')->withAvg('reviews', 'rating')->where('is_active', true)->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))->latest()->paginate(12);
        $this->promotions->decorateProducts($products->getCollection());
        return view('shop.show', compact('seller', 'products'));
    }
}
