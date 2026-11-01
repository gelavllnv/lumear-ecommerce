<?php

namespace App\Http\Controllers;

use App\Models\BundleDeal;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Seller;
use App\Models\SellerVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerPromotionController extends Controller
{
    public function index(Request $request)
    {
        $seller = $this->seller($request);
        $this->refreshStatuses($seller);
        return view('seller.promotions.index', [
            'products' => $seller->products()->with('variants')->orderBy('name')->get(),
            'sales' => ProductSale::with('product')->whereHas('product', fn ($query) => $query->where('seller_id', $seller->id))->latest()->get(),
            'vouchers' => $seller->vouchers()->latest()->get(),
            'flashSales' => $seller->flashSales()->with('product')->latest()->get(),
            'bundles' => $seller->bundleDeals()->with(['qualifierProducts', 'rewardProducts'])->latest()->get(),
        ]);
    }

    public function storeSale(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_price'])],
            'discount_percent' => ['nullable', 'integer', 'between:1,99'],
            'sale_price' => ['nullable', 'decimal:0,2', 'between:1,1000000'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);
        $product = $seller->products()->with('variants')->findOrFail($data['product_id']);
        $regularMin = $product->variants->min('price_minor');
        if ($data['discount_type'] === 'percentage' && ! filled($data['discount_percent'] ?? null)) throw ValidationException::withMessages(['discount_percent' => 'Enter a discount from 1% to 99%.']);
        if ($data['discount_type'] === 'fixed_price') {
            if (! filled($data['sale_price'] ?? null)) throw ValidationException::withMessages(['sale_price' => 'Enter a sale price.']);
            if ((int) round($data['sale_price'] * 100) >= $regularMin) throw ValidationException::withMessages(['sale_price' => 'Sale price must be below the regular product price.']);
        }
        $overlaps = ProductSale::where('product_id', $product->id)->whereIn('status', ['upcoming', 'running'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists();
        if ($overlaps) throw ValidationException::withMessages(['starts_at' => 'This product already has a sale that overlaps that time period.']);
        ProductSale::create(['product_id' => $product->id, 'discount_type' => $data['discount_type'], 'discount_percent' => $data['discount_type'] === 'percentage' ? $data['discount_percent'] : null, 'sale_price_minor' => $data['discount_type'] === 'fixed_price' ? (int) round($data['sale_price'] * 100) : null, 'status' => now()->between($data['starts_at'], $data['ends_at']) ? 'running' : 'upcoming', 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']]);
        return back()->with('status', 'Scheduled sale saved.');
    }

    public function endSale(Request $request, ProductSale $sale)
    {
        $this->ownsProduct($this->seller($request), $sale->product);
        $sale->update(['status' => 'ended', 'ends_at' => now()]);
        return back()->with('status', 'Sale ended.');
    }

    public function storeVoucher(Request $request)
    {
        $seller = $this->seller($request);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Z0-9]{4,12}$/', 'unique:seller_vouchers,code'],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_amount' => ['nullable', 'decimal:0,2', 'min:0.01'],
            'discount_percent' => ['nullable', 'integer', 'between:1,99'],
            'discount_cap' => ['nullable', 'decimal:0,2', 'min:0.01'],
            'minimum_spend' => ['required', 'decimal:0,2', 'min:0'],
            'total_usage_limit' => ['required', 'integer', 'min:1'],
            'per_buyer_limit' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_claimable' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'], 'product_ids.*' => ['integer'],
        ], ['code.regex' => 'Voucher code must use 4 to 12 letters or numbers with no spaces or symbols.']);
        if ($data['per_buyer_limit'] > $data['total_usage_limit']) throw ValidationException::withMessages(['per_buyer_limit' => 'Per-buyer limit cannot exceed the total usage limit.']);
        if ($data['discount_type'] === 'fixed') {
            if (! filled($data['discount_amount'] ?? null)) throw ValidationException::withMessages(['discount_amount' => 'Enter a fixed discount amount.']);
            if ((int) round($data['discount_amount'] * 100) > (int) round($data['minimum_spend'] * 100)) throw ValidationException::withMessages(['discount_amount' => 'The discount cannot exceed the minimum spend.']);
        } elseif (! filled($data['discount_percent'] ?? null) || ! filled($data['discount_cap'] ?? null)) {
            throw ValidationException::withMessages(['discount_percent' => 'Percentage vouchers need a percentage and a positive discount cap.']);
        }
        $voucher = SellerVoucher::create(['seller_id' => $seller->id, 'code' => $data['code'], 'discount_type' => $data['discount_type'], 'discount_minor' => $data['discount_type'] === 'fixed' ? (int) round($data['discount_amount'] * 100) : null, 'discount_percent' => $data['discount_type'] === 'percentage' ? $data['discount_percent'] : null, 'discount_cap_minor' => $data['discount_type'] === 'percentage' ? (int) round($data['discount_cap'] * 100) : null, 'minimum_spend_minor' => (int) round($data['minimum_spend'] * 100), 'total_usage_limit' => $data['total_usage_limit'], 'per_buyer_limit' => $data['per_buyer_limit'], 'is_claimable' => $request->boolean('is_claimable'), 'status' => now()->between($data['starts_at'], $data['ends_at']) ? 'ongoing' : 'upcoming', 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']]);
        $productIds = $seller->products()->whereIn('id', $data['product_ids'] ?? [])->pluck('id');
        $voucher->products()->sync($productIds);
        return back()->with('status', "Voucher {$voucher->code} saved.");
    }

    public function storeFlashSale(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate(['product_id' => ['required', 'integer'], 'flash_price' => ['required', 'decimal:0,2', 'between:1,1000000'], 'quantity_limit' => ['required', 'integer', 'min:1'], 'starts_at' => ['required', 'date']]);
        $product = $seller->products()->with('variants')->findOrFail($data['product_id']);
        if ($data['quantity_limit'] > $product->stock) throw ValidationException::withMessages(['quantity_limit' => 'Flash quantity cannot exceed current stock.']);
        if ((int) round($data['flash_price'] * 100) >= $product->variants->min('price_minor')) throw ValidationException::withMessages(['flash_price' => 'Flash price must be below the regular price.']);
        $startsAt = \Illuminate\Support\Carbon::parse($data['starts_at'], config('app.timezone')); 
        $endsAt = $startsAt->copy()->addDay();
        FlashSale::create(['seller_id' => $seller->id, 'product_id' => $product->id, 'flash_price_minor' => (int) round($data['flash_price'] * 100), 'quantity_limit' => $data['quantity_limit'], 'status' => now()->between($startsAt, $endsAt) ? 'running' : 'upcoming', 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
        return back()->with('status', 'Flash sale scheduled for a 24-hour window.');
    }

    public function storeBundle(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['percentage_bundle', 'buy_x_get_y'])], 'buy_quantity' => ['required', 'integer', 'min:1'], 'get_quantity' => ['nullable', 'integer', 'min:1'], 'discount_percent' => ['nullable', 'integer', 'between:1,99'], 'qualifier_product_ids' => ['required', 'array', 'min:1'], 'reward_product_ids' => ['nullable', 'array']]);
        $qualifierIds = $seller->products()->whereIn('id', $data['qualifier_product_ids'])->pluck('id');
        if ($qualifierIds->count() !== count($data['qualifier_product_ids'])) abort(403);
        if ($data['type'] === 'percentage_bundle' && ! filled($data['discount_percent'] ?? null)) throw ValidationException::withMessages(['discount_percent' => 'Enter a bundle discount percentage.']);
        if ($data['type'] === 'buy_x_get_y' && empty($data['reward_product_ids'])) throw ValidationException::withMessages(['reward_product_ids' => 'Choose the free reward product.']);
        $bundle = BundleDeal::create(['seller_id' => $seller->id, 'name' => $data['name'], 'type' => $data['type'], 'buy_quantity' => $data['buy_quantity'], 'get_quantity' => $data['type'] === 'buy_x_get_y' ? $data['get_quantity'] : 0, 'discount_percent' => $data['type'] === 'percentage_bundle' ? $data['discount_percent'] : null]);
        $bundle->qualifierProducts()->sync($qualifierIds);
        $rewardIds = $seller->products()->whereIn('id', $data['reward_product_ids'] ?? [])->pluck('id');
        $bundle->rewardProducts()->sync($rewardIds);
        return back()->with('status', 'Bundle deal saved.');
    }

    /** Pause/resume is explicit; pricing queries ignore paused and archived campaigns. */
    public function update(Request $request, string $type, int $id)
    {
        $campaign = $this->campaign($request, $type, $id);
        $data = $request->validate(['action' => ['required', Rule::in(['pause', 'resume'])]]);
        if ($type === 'bundle') {
            $campaign->update(['is_active' => $data['action'] === 'resume']);
        } else {
            $campaign->update(['is_paused' => $data['action'] === 'pause']);
        }
        return back()->with('status', ucfirst($type).' '.$data['action'].'d.');
    }

    public function duplicate(Request $request, string $type, int $id)
    {
        $campaign = $this->campaign($request, $type, $id);
        $copy = $campaign->replicate(['used_count', 'quantity_sold', 'archived_at']);
        if ($type === 'voucher') {
            $copy->code = strtoupper(substr($campaign->code, 0, 8)).str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $copy->used_count = 0;
        }
        if ($type === 'flash') $copy->quantity_sold = 0;
        if ($type !== 'bundle') { $copy->is_paused = true; $copy->status = 'upcoming'; }
        $copy->save();
        if ($type === 'bundle') { $copy->qualifierProducts()->sync($campaign->qualifierProducts()->pluck('products.id')); $copy->rewardProducts()->sync($campaign->rewardProducts()->pluck('products.id')); }
        if ($type === 'voucher') $copy->products()->sync($campaign->products()->pluck('products.id'));
        return back()->with('status', ucfirst($type).' duplicated as a paused draft. Review its dates before resuming it.');
    }

    public function archive(Request $request, string $type, int $id)
    {
        $campaign = $this->campaign($request, $type, $id);
        $campaign->update($type === 'bundle' ? ['archived_at' => now(), 'is_active' => false] : ['archived_at' => now(), 'is_paused' => true]);
        return back()->with('status', ucfirst($type).' archived. Historic reporting remains available.');
    }

    private function campaign(Request $request, string $type, int $id): ProductSale|SellerVoucher|FlashSale|BundleDeal
    {
        $seller = $this->seller($request);
        return match ($type) {
            'sale' => tap(ProductSale::with('product')->findOrFail($id), fn (ProductSale $sale) => $this->ownsProduct($seller, $sale->product)),
            'voucher' => $seller->vouchers()->findOrFail($id),
            'flash' => $seller->flashSales()->findOrFail($id),
            'bundle' => $seller->bundleDeals()->findOrFail($id),
            default => abort(404),
        };
    }
    private function refreshStatuses(Seller $seller): void
    {
        ProductSale::whereHas('product', fn ($query) => $query->where('seller_id', $seller->id))->where('status', '!=', 'ended')->where('ends_at', '<=', now())->update(['status' => 'ended']);
        ProductSale::whereHas('product', fn ($query) => $query->where('seller_id', $seller->id))->where('status', 'upcoming')->where('starts_at', '<=', now())->where('ends_at', '>', now())->update(['status' => 'running']);
        $seller->flashSales()->where('status', '!=', 'ended')->where(fn ($query) => $query->where('ends_at', '<=', now())->orWhereColumn('quantity_sold', '>=', 'quantity_limit'))->update(['status' => 'ended']);
        $seller->flashSales()->where('status', 'upcoming')->where('starts_at', '<=', now())->where('ends_at', '>', now())->update(['status' => 'running']);
        $seller->vouchers()->where('status', '!=', 'ended')->where('ends_at', '<=', now())->update(['status' => 'ended']);
        $seller->vouchers()->where('status', 'upcoming')->where('starts_at', '<=', now())->where('ends_at', '>', now())->update(['status' => 'ongoing']);
    }

    private function seller(Request $request): Seller { abort_unless($request->user()?->isSeller(), 403); return $request->user()->seller ?? abort(403); }
    private function ownsProduct(Seller $seller, Product $product): void { abort_unless($product->seller_id === $seller->id, 403); }
}
