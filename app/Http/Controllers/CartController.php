<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BuyerPromotionService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly BuyerPromotionService $promotions) {}

    public function index(Request $request)
    {
        $cart = $this->cart($request)->load([
            'items.productVariant.product.images',
            'items.productVariant.product.seller',
            'items.productVariant.product.sales',
            'items.productVariant.product.flashSales',
        ]);
        $deliveryAddress = $request->user()->addresses()->where('is_default', true)->first() ?? $request->user()->addresses()->first();
        $pricing = $cart->items->mapWithKeys(fn ($item) => [
            $item->id => $this->promotions->priceFor($item->productVariant->product, $item->productVariant),
        ]);
        $subtotal = $cart->items->sum(fn ($item) => $pricing[$item->id]['price_minor'] * $item->quantity);
        $bundleBasePricing = $cart->items->mapWithKeys(fn ($item) => [$item->id => [
            'unit_price_minor' => $pricing[$item->id]['price_minor'],
            'line_total_minor' => $pricing[$item->id]['price_minor'] * $item->quantity,
            'promotion_discount_minor' => 0,
        ]]);
        $bundlePricing = $this->promotions->bundleDiscountsForCart($cart->items, $bundleBasePricing);
        $claimedVouchers = $this->promotions->claimedVouchersForCart($request->user(), $cart->items);

        return view('cart.index', compact('cart', 'deliveryAddress', 'pricing', 'subtotal', 'bundlePricing', 'claimedVouchers'));
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($request->user()?->isBuyer(), 403);
        abort_unless($product->is_active, 404);
        abort_if($product->seller->is_on_vacation, 422, 'This shop is on vacation. You cannot add its products to your bag right now.');
        $data = $request->validate(['variant_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1']]);
        $variant = ProductVariant::query()->whereKey($data['variant_id'])->where('product_id', $product->id)->where('is_active', true)->firstOrFail();
        abort_if($variant->stock < $data['quantity'], 422, 'That quantity is no longer available.');

        $cart = $this->cart($request);
        $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'product_variant_id' => $variant->id]);
        $item->quantity = min($variant->stock, ($item->exists ? $item->quantity : 0) + $data['quantity']);
        $item->selected = true;
        $item->save();

        return $request->expectsJson() ? response()->json(['id' => $item->id]) : back()->with('status', 'Added to your bag.');
    }

    public function update(Request $request, CartItem $item)
    {
        abort_unless($item->cart_id === $this->cart($request)->id, 403);
        $item->load('productVariant');
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.$item->productVariant->stock]]);
        $data['quantity'] === 0 ? $item->delete() : $item->update(['quantity' => $data['quantity']]);
        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('status', 'Your bag was updated.');
    }

    public function destroy(Request $request, CartItem $item)
    {
        abort_unless($item->cart_id === $this->cart($request)->id, 403);

        $item->delete();

        return $request->expectsJson() ? response()->json(['success' => true]) : redirect()->route('cart')->with('status', 'Item removed from your bag.');
    }

    private function cart(Request $request): Cart
    {
        abort_unless($request->user()?->isBuyer(), 403);
        return $request->user()->cart()->firstOrCreate();
    }
}
