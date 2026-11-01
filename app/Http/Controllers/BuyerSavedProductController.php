<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SavedProduct;
use Illuminate\Http\Request;

class BuyerSavedProductController extends Controller
{
    public function index(Request $request)
    {
        $savedProducts = $request->user()->savedProducts()->with(['product.seller', 'product.images', 'product.primaryVariant'])->latest()->paginate(24);
        return view('buyer.saved.index', compact('savedProducts'));
    }

    public function store(Request $request, Product $product)
    {
        abort_unless($request->user()?->isBuyer() && $product->is_active && $product->seller->isApproved(), 403);
        SavedProduct::firstOrCreate(['buyer_id' => $request->user()->id, 'product_id' => $product->id]);
        return back()->with('status', 'Saved to your favourites.');
    }

    public function destroy(Request $request, Product $product)
    {
        SavedProduct::where('buyer_id', $request->user()->id)->where('product_id', $product->id)->delete();
        return back()->with('status', 'Removed from your favourites.');
    }
}
