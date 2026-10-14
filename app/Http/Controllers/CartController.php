<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->cart($request)->load(['items.productVariant.product.images', 'items.productVariant.product.seller']);
        $deliveryAddress = $request->user()->addresses()->where('is_default', true)->first() ?? $request->user()->addresses()->first();

        return view('cart.index', compact('cart', 'deliveryAddress'));
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($request->user()?->isBuyer(), 403);
        abort_unless($product->is_active, 404);
        $data = $request->validate(['variant_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1']]);
        $variant = ProductVariant::query()->whereKey($data['variant_id'])->where('product_id', $product->id)->where('is_active', true)->firstOrFail();
        abort_if($variant->stock < $data['quantity'], 422, 'That quantity is no longer available.');

        $cart = $this->cart($request);
        $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'product_variant_id' => $variant->id]);
        $item->quantity = min($variant->stock, ($item->exists ? $item->quantity : 0) + $data['quantity']);
        $item->selected = true;
        $item->save();

        return back()->with('status', 'Added to your bag.');
    }

    public function update(Request $request, CartItem $item)
    {
        abort_unless($item->cart_id === $this->cart($request)->id, 403);
        $item->load('productVariant');
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.$item->productVariant->stock]]);
        $data['quantity'] === 0 ? $item->delete() : $item->update(['quantity' => $data['quantity']]);
        return back()->with('status', 'Your bag was updated.');
    }

    public function destroy(Request $request, CartItem $item)
    {
        abort_unless($item->cart_id === $this->cart($request)->id, 403);

        $item->delete();

        return redirect()->route('cart')->with('status', 'Item removed from your bag.');
    }

    private function cart(Request $request): Cart
    {
        abort_unless($request->user()?->isBuyer(), 403);
        return $request->user()->cart()->firstOrCreate();
    }
}
