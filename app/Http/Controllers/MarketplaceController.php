<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['seller', 'category', 'images', 'variants', 'primaryVariant'])
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

        return view('marketplace.index', ['products' => $products, 'categories' => Category::where('is_active', true)->orderBy('position')->get()]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active && $product->seller->isApproved(), 404);
        $product->loadCount('reviews')->loadAvg('reviews', 'rating')->load(['category', 'seller', 'images', 'variants' => fn ($query) => $query->where('is_active', true)->where('stock', '>', 0)->orderBy('price_minor')]);
        abort_if($product->variants->isEmpty(), 404);
        return view('marketplace.show', compact('product'));
    }

    public function shop(Seller $seller)
    {
        abort_unless($seller->isApproved(), 404);
        $products = $seller->products()->with(['images', 'variants', 'primaryVariant'])->withCount('reviews')->withAvg('reviews', 'rating')->where('is_active', true)->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))->latest()->paginate(12);
        return view('shop.show', compact('seller', 'products'));
    }
}
