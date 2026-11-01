@extends('layouts.seller')

@section('title', 'Campaign Studio — Lumear')

@section('seller-content')
@php
    $runningCampaigns = $sales->where('status', 'running')->count()
        + $flashSales->where('status', 'running')->count()
        + $vouchers->where('status', 'ongoing')->count();
    $upcomingCampaigns = $sales->where('status', 'upcoming')->count()
        + $flashSales->where('status', 'upcoming')->count()
        + $vouchers->where('status', 'upcoming')->count();
    $claimedVoucherUses = $vouchers->sum('used_count');
@endphp

<div class="seller-page promotion-studio">
    <header class="promotion-hero">
        <div>
            <p class="seller-page-eyebrow">Seller-funded marketing</p>
            <h1>Campaign Studio</h1>
            <p>Make every offer easy to find, understand, and manage—from your next voucher to a limited flash drop.</p>
        </div>
        <a class="promotion-hero-action" href="#create-voucher"><span aria-hidden="true">＋</span> Create a promotion</a>
    </header>

    <section class="promotion-summary" aria-label="Promotion summary">
        <article><span class="promotion-summary-icon" aria-hidden="true">✦</span><div><strong>{{ $runningCampaigns }}</strong><span>Live now</span></div></article>
        <article><span class="promotion-summary-icon" aria-hidden="true">◷</span><div><strong>{{ $upcomingCampaigns }}</strong><span>Scheduled</span></div></article>
        <article><span class="promotion-summary-icon" aria-hidden="true">⌁</span><div><strong>{{ number_format($claimedVoucherUses) }}</strong><span>Voucher uses</span></div></article>
        <article><span class="promotion-summary-icon" aria-hidden="true">▦</span><div><strong>{{ $products->count() }}</strong><span>Products ready</span></div></article>
    </section>

    <section class="promotion-launcher" aria-labelledby="promotion-launcher-title">
        <div class="promotion-section-heading">
            <div><p class="seller-page-eyebrow">Start with a goal</p><h2 id="promotion-launcher-title">What would you like to launch?</h2></div>
            <p>Choose an offer type, then set the value, dates, and eligible products.</p>
        </div>
        <div class="promotion-launcher-grid">
            <a href="#create-voucher" class="promotion-choice"><span class="promotion-choice-icon">%</span><strong>Store voucher</strong><small>Reward shoppers with a code or claimable offer.</small><b>Build voucher <span>→</span></b></a>
            <a href="#create-flash-sale" class="promotion-choice"><span class="promotion-choice-icon">ϟ</span><strong>Flash sale</strong><small>Move a limited quantity at a special price.</small><b>Set flash price <span>→</span></b></a>
            <a href="#create-sale" class="promotion-choice"><span class="promotion-choice-icon">↓</span><strong>Product discount</strong><small>Schedule a reduction for one product.</small><b>Schedule sale <span>→</span></b></a>
            <a href="#create-bundle" class="promotion-choice"><span class="promotion-choice-icon">＋</span><strong>Bundle deal</strong><small>Encourage larger baskets with a multi-item offer.</small><b>Create bundle <span>→</span></b></a>
        </div>
    </section>

    <section class="promotion-section" aria-labelledby="promotion-create-title">
        <div class="promotion-section-heading">
            <div><p class="seller-page-eyebrow">Campaign builder</p><h2 id="promotion-create-title">Create an offer</h2></div>
            <p>All times follow your store’s local time. Campaigns are checked automatically when you return here.</p>
        </div>

        <div class="promotion-builder-grid">
            <article class="promotion-builder-card" id="create-voucher">
                <div class="promotion-card-heading"><span class="promotion-type-icon">%</span><div><p class="seller-page-eyebrow">Most flexible</p><h3>Store voucher</h3></div></div>
                <p class="promotion-card-intro">Create a code for a set amount, or a percentage with a savings cap. You can keep it store-wide or limit it to selected products.</p>
                <form class="seller-operation-form promotion-form" method="post" action="{{ route('seller.promotions.vouchers.store') }}">
                    @csrf
                    <div class="promotion-form-grid">
                        <label class="mini-label">Voucher code<input class="field uppercase" name="code" value="{{ old('code') }}" minlength="4" maxlength="12" placeholder="WELCOME15" required><span>4–12 letters or numbers</span></label>
                        <label class="mini-label">Discount type<select class="field" name="discount_type" data-discount-type="voucher"><option value="fixed" @selected(old('discount_type') === 'fixed')>Fixed amount</option><option value="percentage" @selected(old('discount_type') === 'percentage')>Percentage with cap</option></select></label>
                        <label class="mini-label" data-voucher-fixed>Amount off (PHP)<input class="field" name="discount_amount" value="{{ old('discount_amount') }}" type="number" min=".01" step=".01" placeholder="100.00"></label>
                        <label class="mini-label" data-voucher-percentage hidden>Percentage off<input class="field" name="discount_percent" value="{{ old('discount_percent') }}" type="number" min="1" max="99" placeholder="15"></label>
                        <label class="mini-label" data-voucher-percentage hidden>Maximum savings (PHP)<input class="field" name="discount_cap" value="{{ old('discount_cap') }}" type="number" min=".01" step=".01" placeholder="250.00"></label>
                        <label class="mini-label">Minimum spend (PHP)<input class="field" name="minimum_spend" value="{{ old('minimum_spend', 0) }}" type="number" min="0" step=".01" required></label>
                        <label class="mini-label">Total redemptions<input class="field" name="total_usage_limit" value="{{ old('total_usage_limit') }}" type="number" min="1" placeholder="50" required></label>
                        <label class="mini-label">Per shopper<input class="field" name="per_buyer_limit" value="{{ old('per_buyer_limit', 1) }}" type="number" min="1" required></label>
                        <label class="mini-label">Starts<input class="field" name="starts_at" value="{{ old('starts_at') }}" type="datetime-local" required></label>
                        <label class="mini-label">Ends<input class="field" name="ends_at" value="{{ old('ends_at') }}" type="datetime-local" required></label>
                        <label class="mini-label promotion-span-full">Eligible products <select class="field" name="product_ids[]" multiple aria-describedby="voucher-products-help">@foreach($products as $product)<option value="{{ $product->id }}" @selected(in_array($product->id, old('product_ids', [])))>{{ $product->name }}</option>@endforeach</select><span id="voucher-products-help">Leave nothing selected to make this voucher available across your shop. Hold Ctrl or ⌘ to choose several products.</span></label>
                        <label class="promotion-check promotion-span-full"><input type="hidden" name="is_claimable" value="0"><input type="checkbox" name="is_claimable" value="1" @checked(old('is_claimable', true))><span><strong>Make this claimable</strong><small>Shoppers can save it to their voucher wallet before checkout.</small></span></label>
                    </div>
                    <button class="promotion-submit" type="submit">Create voucher <span aria-hidden="true">→</span></button>
                </form>
            </article>

            <article class="promotion-builder-card" id="create-flash-sale">
                <div class="promotion-card-heading"><span class="promotion-type-icon">ϟ</span><div><p class="seller-page-eyebrow">Limited release</p><h3>Flash sale</h3></div></div>
                <p class="promotion-card-intro">Set a temporary price and quantity cap. Every flash sale runs for 24 hours, or ends sooner when the quantity sells out.</p>
                <form class="seller-operation-form promotion-form" method="post" action="{{ route('seller.promotions.flash-sales.store') }}">
                    @csrf
                    <div class="promotion-form-grid">
                        <label class="mini-label promotion-span-full">Product<select class="field" name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} · {{ $product->stock }} available</option>@endforeach</select></label>
                        <label class="mini-label">Flash price (PHP)<input class="field" name="flash_price" value="{{ old('flash_price') }}" type="number" min="1" step=".01" required></label>
                        <label class="mini-label">Quantity available<input class="field" name="quantity_limit" value="{{ old('quantity_limit') }}" type="number" min="1" required></label>
                        <label class="mini-label">Starts<input class="field" name="starts_at" value="{{ old('starts_at') }}" type="datetime-local" required></label>
                        <label class="mini-label"><span>24-hour flash window</span><span>Starts at your chosen time and ends automatically 24 hours later.</span></label>
                    </div>
                    <button class="promotion-submit" type="submit">Create flash sale <span aria-hidden="true">→</span></button>
                </form>
            </article>

            <article class="promotion-builder-card" id="create-sale">
                <div class="promotion-card-heading"><span class="promotion-type-icon">↓</span><div><p class="seller-page-eyebrow">Timed discount</p><h3>Product sale</h3></div></div>
                <p class="promotion-card-intro">Schedule either a percentage reduction or a fixed sale price. We prevent overlapping campaigns for the same product.</p>
                <form class="seller-operation-form promotion-form" method="post" action="{{ route('seller.promotions.sales.store') }}">
                    @csrf
                    <div class="promotion-form-grid">
                        <label class="mini-label promotion-span-full">Product<select class="field" name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select></label>
                        <label class="mini-label">Discount type<select class="field" name="discount_type" data-discount-type="sale"><option value="percentage" @selected(old('discount_type', 'percentage') === 'percentage')>Percentage off</option><option value="fixed_price" @selected(old('discount_type') === 'fixed_price')>Fixed sale price</option></select></label>
                        <label class="mini-label" data-sale-percentage>Percentage off<input class="field" name="discount_percent" value="{{ old('discount_percent') }}" type="number" min="1" max="99" placeholder="15"></label>
                        <label class="mini-label" data-sale-price hidden>Sale price (PHP)<input class="field" name="sale_price" value="{{ old('sale_price') }}" type="number" min="1" step=".01" placeholder="499.00"></label>
                        <label class="mini-label">Starts<input class="field" name="starts_at" value="{{ old('starts_at') }}" type="datetime-local" required></label>
                        <label class="mini-label">Ends<input class="field" name="ends_at" value="{{ old('ends_at') }}" type="datetime-local" required></label>
                    </div>
                    <button class="promotion-submit" type="submit">Schedule sale <span aria-hidden="true">→</span></button>
                </form>
            </article>

            <article class="promotion-builder-card" id="create-bundle">
                <div class="promotion-card-heading"><span class="promotion-type-icon">＋</span><div><p class="seller-page-eyebrow">Larger baskets</p><h3>Bundle deal</h3></div></div>
                <p class="promotion-card-intro">Offer a bundle percentage or pair qualifying products with a free reward. Choose the products that make the offer work.</p>
                <form class="seller-operation-form promotion-form" method="post" action="{{ route('seller.promotions.bundles.store') }}">
                    @csrf
                    <div class="promotion-form-grid">
                        <label class="mini-label">Bundle name<input class="field" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Weekend pair" required></label>
                        <label class="mini-label">Offer type<select class="field" name="type" data-bundle-type><option value="percentage_bundle" @selected(old('type', 'percentage_bundle') === 'percentage_bundle')>Bundle percentage</option><option value="buy_x_get_y" @selected(old('type') === 'buy_x_get_y')>Buy X, get Y</option></select></label>
                        <label class="mini-label">Buy quantity<input class="field" name="buy_quantity" value="{{ old('buy_quantity', 2) }}" type="number" min="1" required></label>
                        <label class="mini-label" data-bundle-percentage>Bundle discount %<input class="field" name="discount_percent" value="{{ old('discount_percent') }}" type="number" min="1" max="99" placeholder="10"></label>
                        <label class="mini-label" data-bundle-reward hidden>Free quantity<input class="field" name="get_quantity" value="{{ old('get_quantity') }}" type="number" min="1" placeholder="1"></label>
                        <label class="mini-label promotion-span-full">Qualifying products<select class="field" name="qualifier_product_ids[]" multiple required>@foreach($products as $product)<option value="{{ $product->id }}" @selected(in_array($product->id, old('qualifier_product_ids', [])))>{{ $product->name }}</option>@endforeach</select><span>Hold Ctrl or ⌘ to select products.</span></label>
                        <label class="mini-label promotion-span-full" data-bundle-reward hidden>Reward products<select class="field" name="reward_product_ids[]" multiple>@foreach($products as $product)<option value="{{ $product->id }}" @selected(in_array($product->id, old('reward_product_ids', [])))>{{ $product->name }}</option>@endforeach</select></label>
                    </div>
                    <button class="promotion-submit" type="submit">Save bundle <span aria-hidden="true">→</span></button>
                </form>
            </article>
        </div>
    </section>

    <section class="promotion-section promotion-management" aria-labelledby="promotion-management-title">
        <div class="promotion-section-heading"><div><p class="seller-page-eyebrow">Campaign health</p><h2 id="promotion-management-title">Manage your offers</h2></div><p>Live and scheduled campaigns appear first. End a product discount early whenever plans change.</p></div>
        <div class="promotion-management-grid">
            <article class="promotion-management-card"><div class="promotion-management-heading"><span>↓</span><div><h3>Product sales</h3><p>{{ $sales->count() }} total</p></div></div>@forelse($sales as $sale)<div class="promotion-row"><div><span class="promotion-status status-{{ $sale->status }}">{{ $sale->status }}</span><strong>{{ $sale->product->name }}</strong><small>{{ $sale->discount_type === 'percentage' ? $sale->discount_percent.'% off' : 'Sale price ₱'.number_format($sale->sale_price_minor / 100, 2) }} · Ends {{ $sale->ends_at->format('M j, g:i A') }}</small></div>@if($sale->status !== 'ended')<form method="post" action="{{ route('seller.promotions.sales.end', $sale) }}">@csrf<button class="promotion-end" type="submit">End early</button></form>@endif</div>@empty<div class="promotion-empty">No product discounts yet. Schedule one above to highlight a product.</div>@endforelse</article>
            <article class="promotion-management-card"><div class="promotion-management-heading"><span>%</span><div><h3>Store vouchers</h3><p>{{ $vouchers->count() }} total</p></div></div>@forelse($vouchers as $voucher)<div class="promotion-row"><div><span class="promotion-status status-{{ $voucher->status }}">{{ $voucher->status }}</span><strong>{{ $voucher->code }}</strong><small>{{ $voucher->discount_type === 'fixed' ? '₱'.number_format($voucher->discount_minor / 100, 2).' off' : $voucher->discount_percent.'% off, capped at ₱'.number_format($voucher->discount_cap_minor / 100, 2) }} · {{ $voucher->used_count }}/{{ $voucher->total_usage_limit }} used</small></div></div>@empty<div class="promotion-empty">No vouchers yet. Create a claimable offer for shoppers to save.</div>@endforelse</article>
            <article class="promotion-management-card"><div class="promotion-management-heading"><span>ϟ</span><div><h3>Flash sales</h3><p>{{ $flashSales->count() }} total</p></div></div>@forelse($flashSales as $flashSale)<div class="promotion-row"><div><span class="promotion-status status-{{ $flashSale->status }}">{{ $flashSale->status }}</span><strong>{{ $flashSale->product->name }}</strong><small>₱{{ number_format($flashSale->flash_price_minor / 100, 2) }} · {{ $flashSale->quantity_sold }}/{{ $flashSale->quantity_limit }} claimed · Ends {{ $flashSale->ends_at->format('M j, g:i A') }}</small></div></div>@empty<div class="promotion-empty">No flash sales yet. Use one for a limited stock moment.</div>@endforelse</article>
            <article class="promotion-management-card"><div class="promotion-management-heading"><span>＋</span><div><h3>Bundle deals</h3><p>{{ $bundles->count() }} total</p></div></div>@forelse($bundles as $bundle)<div class="promotion-row"><div><span class="promotion-status status-bundle">bundle</span><strong>{{ $bundle->name }}</strong><small>{{ $bundle->type === 'percentage_bundle' ? $bundle->discount_percent.'% off when buying '.$bundle->buy_quantity : 'Buy '.$bundle->buy_quantity.', get '.$bundle->get_quantity.' free' }} · {{ $bundle->qualifierProducts->count() }} qualifying product{{ $bundle->qualifierProducts->count() === 1 ? '' : 's' }}</small></div></div>@empty<div class="promotion-empty">No bundle deals yet. Pair complementary products to encourage a larger order.</div>@endforelse</article>
        </div>
    </section>
    <section class="promotion-section" aria-labelledby="promotion-controls-title">
        <div class="promotion-section-heading"><div><p class="seller-page-eyebrow">Lifecycle controls</p><h2 id="promotion-controls-title">Pause, duplicate, or archive</h2></div><p>Paused and archived offers are excluded from storefront pricing and checkout verification.</p></div>
        <div class="promotion-management-grid">
        @foreach(['sale' => $sales, 'voucher' => $vouchers, 'flash' => $flashSales, 'bundle' => $bundles] as $type => $campaigns)
          <article class="promotion-management-card"><h3>{{ ucfirst($type) }} campaigns</h3>
          @forelse($campaigns->whereNull('archived_at') as $campaign)<div class="promotion-row"><strong>{{ $campaign->name ?? $campaign->code ?? $campaign->product->name }}</strong><div class="flex gap-2"><form method="post" action="{{ route('seller.promotions.update', [$type, $campaign->id]) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ ($campaign->is_paused ?? !$campaign->is_active) ? 'resume' : 'pause' }}"><button class="promotion-end" type="submit">{{ ($campaign->is_paused ?? !$campaign->is_active) ? 'Resume' : 'Pause' }}</button></form><form method="post" action="{{ route('seller.promotions.duplicate', [$type, $campaign->id]) }}">@csrf<button class="promotion-end" type="submit">Duplicate</button></form><form method="post" action="{{ route('seller.promotions.archive', [$type, $campaign->id]) }}">@csrf<button class="promotion-end" type="submit">Archive</button></form></div></div>@empty <p class="promotion-empty">No campaigns to manage.</p>@endforelse</article>
        @endforeach
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const toggle = (selector, visible) => document.querySelectorAll(selector).forEach((element) => element.hidden = !visible);
        const syncVoucher = () => { const value = document.querySelector('[data-discount-type="voucher"]')?.value; toggle('[data-voucher-fixed]', value === 'fixed'); toggle('[data-voucher-percentage]', value === 'percentage'); };
        const syncSale = () => { const value = document.querySelector('[data-discount-type="sale"]')?.value; toggle('[data-sale-percentage]', value === 'percentage'); toggle('[data-sale-price]', value === 'fixed_price'); };
        const syncBundle = () => { const value = document.querySelector('[data-bundle-type]')?.value; toggle('[data-bundle-percentage]', value === 'percentage_bundle'); toggle('[data-bundle-reward]', value === 'buy_x_get_y'); };
        document.querySelector('[data-discount-type="voucher"]')?.addEventListener('change', syncVoucher);
        document.querySelector('[data-discount-type="sale"]')?.addEventListener('change', syncSale);
        document.querySelector('[data-bundle-type]')?.addEventListener('change', syncBundle);
        syncVoucher(); syncSale(); syncBundle();
    })();
</script>
@endpush
