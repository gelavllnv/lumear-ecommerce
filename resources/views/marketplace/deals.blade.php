@extends('layouts.app')

@section('title', 'Deals & bundles — Lumear')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buyer/deals.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/buyer/flash-deals.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/buyer/flash-pricing.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/buyer/deals-products.css') }}?v=1">
@endpush

@section('content')
    <div class="deals-hub rise">
        <header class="deals-hero">
            <div class="deals-hero-glow deals-hero-glow-one" aria-hidden="true"></div>
            <div class="deals-hero-glow deals-hero-glow-two" aria-hidden="true"></div>
            <div class="deals-hero-copy">
                <p class="deals-kicker"><span aria-hidden="true">✦</span> Curated savings, simply applied</p>
                <h1>More reasons to<br><em>love your bag.</em></h1>
                <p>Discover limited-time prices, shop vouchers, and thoughtful multi-item offers from independent Lumear stores.</p>
                <div class="deals-hero-actions">
                    <a href="#bundle-offers" class="deals-primary-action">Explore bundles <span aria-hidden="true">↓</span></a>
                    <a href="{{ route('shop') }}" class="deals-secondary-action">Browse all products</a>
                </div>
            </div>
            <div class="deals-hero-panel" aria-label="How Lumear offers work">
                <span class="deals-hero-panel-label">A better way to save</span>
                <div class="deals-hero-rule"><span>1</span><p><strong>Find an offer</strong><small>Choose a deal that feels right for you.</small></p></div>
                <div class="deals-hero-rule"><span>2</span><p><strong>Add what qualifies</strong><small>Bundle details are always shown before you buy.</small></p></div>
                <div class="deals-hero-rule"><span>3</span><p><strong>We do the maths</strong><small>The best eligible bundle per shop is applied in your bag.</small></p></div>
            </div>
        </header>

        <nav class="deals-jump-nav" aria-label="Browse offers">
            <a href="#flash-deals"><span class="deals-jump-icon">ϟ</span><span><strong>Flash deals</strong><small>Limited-time prices</small></span><b>{{ $flashProducts->count() }}</b></a>
            <a href="#shop-vouchers"><span class="deals-jump-icon">%</span><span><strong>Shop vouchers</strong><small>Save for checkout</small></span><b>{{ $claimableVouchers->count() }}</b></a>
            <a href="#bundle-offers"><span class="deals-jump-icon">＋</span><span><strong>Bundle offers</strong><small>Automatic savings</small></span><b>{{ $activeBundles->count() }}</b></a>
        </nav>

        <section class="deals-content-section" id="flash-deals" aria-labelledby="deals-flash-title">
            <div class="deals-section-heading">
                <div><p class="deals-kicker">Limited-time prices</p><h2 id="deals-flash-title">Flash deals <em>happening now.</em></h2></div>
                <a href="{{ route('shop') }}">Browse all products <span aria-hidden="true">→</span></a>
            </div>
            @if($flashProducts->isNotEmpty())
                <div class="product-grid flash-grid deals-product-grid">@foreach($flashProducts as $product) @include('buyer.partials.home-product-card', ['product' => $product]) @endforeach</div>
            @else
                <div class="deals-empty-state"><span aria-hidden="true">ϟ</span><div><strong>No flash deals right now.</strong><p>Fresh limited-time prices are added by stores regularly. Explore the other ways to save below.</p></div></div>
            @endif
        </section>

        <section class="deals-content-section deals-section-tinted" id="shop-vouchers" aria-labelledby="deals-voucher-title">
            <div class="deals-section-heading">
                <div><p class="deals-kicker">Save for later</p><h2 id="deals-voucher-title">Vouchers from <em>local shops.</em></h2></div>
                <p>Claim one now, then select it in your bag at COD checkout.</p>
            </div>
            @if($claimableVouchers->isNotEmpty())
                <div class="deals-offer-grid">
                    @foreach($claimableVouchers as $voucher)
                        <article class="deals-offer-card deals-voucher-card">
                            <div class="deals-offer-topline"><span class="deals-offer-tag">Shop voucher</span><span class="deals-offer-store">{{ $voucher->seller->name }}</span></div>
                            <div class="deals-voucher-value">{{ $voucher->discount_type === 'fixed' ? '₱'.number_format($voucher->discount_minor / 100, 0) : $voucher->discount_percent.'%' }}<small>{{ $voucher->discount_type === 'fixed' ? 'off your order' : 'off your order' }}</small></div>
                            <p class="deals-offer-detail">Min. spend ₱{{ number_format($voucher->minimum_spend_minor / 100, 2) }} <i aria-hidden="true">·</i> Ends {{ $voucher->ends_at->format('M j, g:i A') }}</p>
                            <div class="deals-offer-footer">
                                <span class="deals-code">{{ $voucher->code }}</span>
                                @auth
                                    @if(auth()->user()->isBuyer())
                                        @if($claimedVoucherIds->contains($voucher->id))<span class="deals-claimed">Saved</span>@else<form method="post" action="{{ route('buyer.vouchers.claim', $voucher) }}">@csrf<button type="submit">Claim offer <span aria-hidden="true">→</span></button></form>@endif
                                    @else<a href="{{ route('shop') }}">Buyer accounts can claim</a>@endif
                                @else<a href="{{ route('login') }}">Sign in to claim <span aria-hidden="true">→</span></a>@endauth
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="deals-empty-state"><span aria-hidden="true">%</span><div><strong>No claimable vouchers today.</strong><p>Once a shop publishes a voucher, you’ll be able to save it here for your next checkout.</p></div></div>
            @endif
        </section>

        <section class="deals-content-section" id="bundle-offers" aria-labelledby="deals-bundle-title">
            <div class="deals-section-heading">
                <div><p class="deals-kicker">Made for more</p><h2 id="deals-bundle-title">Bundle offers, <em>automatically applied.</em></h2></div>
                <p>Add the qualifying products to your bag. Lumear applies the best eligible bundle from each shop automatically.</p>
            </div>
            @if($activeBundles->isNotEmpty())
                <div class="deals-offer-grid">
                    @foreach($activeBundles as $bundle)
                        <article class="deals-offer-card deals-bundle-card">
                            <div class="deals-offer-topline"><span class="deals-offer-tag">{{ $bundle->type === 'percentage_bundle' ? 'Bundle saving' : 'Buy & get' }}</span><span class="deals-offer-store">{{ $bundle->seller->name }}</span></div>
                            <h3>{{ $bundle->name }}</h3>
                            <p class="deals-bundle-value">{{ $bundle->type === 'percentage_bundle' ? $bundle->discount_percent.'% off' : 'Buy '.$bundle->buy_quantity.', get '.$bundle->get_quantity.' free' }}</p>
                            <div class="deals-bundle-steps"><p><span>1</span><b>Add</b> {{ $bundle->buy_quantity }} qualifying item{{ $bundle->buy_quantity === 1 ? '' : 's' }}</p>@if($bundle->type === 'buy_x_get_y')<p><span>2</span><b>Choose</b> an eligible reward</p>@endif</div>
                            <p class="deals-offer-detail">Qualifying: {{ $bundle->qualifierProducts->pluck('name')->join(', ') }}@if($bundle->type === 'buy_x_get_y') <i aria-hidden="true">·</i> Rewards: {{ $bundle->rewardProducts->pluck('name')->join(', ') }}@endif</p>
                            <a class="deals-bundle-action" href="{{ route('shops.show', $bundle->seller) }}">Shop this offer <span aria-hidden="true">→</span></a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="deals-empty-state"><span aria-hidden="true">＋</span><div><strong>No active bundle offers today.</strong><p>When a shop adds one, its details will live here and the saving will be worked out in your bag.</p></div></div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/buyer/flash-deals.js') }}?v=2" defer></script>
@endpush
