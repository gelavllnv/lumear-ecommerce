@extends('layouts.app')

@section('title', 'Lumear — Discover your everyday')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buyer/flash-deals.css') }}?v=1">
@endpush

@section('content')
    <div class="lumear-home">
        <section class="container hero-section" aria-label="Featured Lumear collections">
            <div class="hero-carousel" id="heroSlider">
                <div class="hero-track" id="heroTrack">
                    <article class="hero-slide hero-home active">
                        <div class="hero-copy">
                            <span class="hero-kicker">LOCAL FINDS · COD CHECKOUT</span>
                            <h1>Thoughtful finds,<br><em>made for everyday.</em></h1>
                            <p>Discover products from independent Lumear stores, with clear prices and cash-on-delivery checkout.</p>
                            <a href="#recommended" class="hero-button">Explore products <span>↗</span></a>
                        </div>
                        <span class="hero-caption">Find your next favorite.</span>
                    </article>

                    <article class="hero-slide hero-fashion">
                        <div class="hero-copy">
                            <span class="hero-kicker">SHOP LOCAL, SHOP WELL</span>
                            <h1>Fresh arrivals,<br><em>ready to discover.</em></h1>
                            <p>Browse new listings from verified sellers and find something that fits your everyday life.</p>
                            <a href="{{ route('shop') }}" class="hero-button">Shop all finds <span>↗</span></a>
                        </div>
                        <span class="hero-caption">A good find awaits.</span>
                    </article>

                    <article class="hero-slide hero-beauty">
                        <div class="hero-copy">
                            <span class="hero-kicker">MADE FOR YOUR MOMENTS</span>
                            <h1>Small details,<br><em>lovely moments.</em></h1>
                            <p>From useful essentials to thoughtful gifts, Lumear brings good products together in one place.</p>
                            <a href="#categories" class="hero-button">Browse categories <span>↗</span></a>
                        </div>
                        <span class="hero-caption">Made to be shared.</span>
                    </article>
                </div>

                <button type="button" class="hero-arrow hero-prev" id="heroPrev" aria-label="Previous featured collection"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg></button>
                <button type="button" class="hero-arrow hero-next" id="heroNext" aria-label="Next featured collection"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg></button>
                <div class="hero-bottom"><div class="hero-dots" id="heroPagination"><button type="button" class="active" aria-label="Show collection 1"></button><button type="button" aria-label="Show collection 2"></button><button type="button" aria-label="Show collection 3"></button></div><span id="heroSlideCounter">01 / 03</span></div>
                <div class="hero-progress"><span id="heroProgress"></span></div>
            </div>
        </section>

        <section class="container category-section" id="categories" aria-label="Shop by category">
            <div class="category-strip">
                @forelse($categories->take(12) as $category)
                    <a class="category-tile" href="{{ route('shop', ['q' => $category->name]) }}"><span class="category-icon">✦</span><span>{{ $category->name }}</span></a>
                @empty
                    <p class="empty-state">Categories will appear here as an administrator adds them.</p>
                @endforelse
            </div>
        </section>

        <section class="container collection-section" id="collections">
            <div class="section-heading"><div><span class="eyebrow">DISCOVER SOMETHING NEW</span><h2>Curated for <em>your everyday.</em></h2></div><p>Good things from Lumear’s independent stores.</p></div>
            <div class="promo-grid">
                <a href="{{ route('shop', ['q' => 'Fashion']) }}" class="promo-card promo-fashion"><div class="promo-copy"><span>FRESH FINDS</span><h3>Style for<br><em>everyday.</em></h3><p>Discover something that feels like you.</p><strong>Shop now ↗</strong></div></a>
                <a href="{{ route('shop', ['q' => 'Beauty']) }}" class="promo-card promo-beauty"><div class="promo-copy"><span>SELF-CARE EDIT</span><h3>Little rituals,<br><em>lovely moments.</em></h3><p>Make room for the things you enjoy.</p><strong>Explore ↗</strong></div></a>
                <a href="{{ route('shop', ['q' => 'Home']) }}" class="promo-card promo-work"><div class="promo-copy"><span>HOME & LIVING</span><h3>Better spaces,<br><em>brighter days.</em></h3><p>Bring warmth to your everyday corners.</p><strong>Discover ↗</strong></div></a>
            </div>
        </section>

        @if($flashProducts->isNotEmpty())
            <section class="container deals-panel" aria-labelledby="flash-deals-title">
                <div class="deals-heading"><div class="deals-heading-left"><span class="eyebrow">LIMITED-TIME PRICES</span><h2 id="flash-deals-title">Flash deals <em>happening now.</em></h2></div><a href="#recommended">See all products ↗</a></div>
                <div class="product-grid flash-grid">
                    @foreach($flashProducts as $product)
                        @include('buyer.partials.home-product-card', ['product' => $product])
                    @endforeach
                </div>
            </section>
        @endif

        @if($claimableVouchers->isNotEmpty())
            <section class="container voucher-section" aria-labelledby="voucher-title">
                <div class="section-heading"><div><span class="eyebrow">SAVE ON YOUR FINDS</span><h2 id="voucher-title">Vouchers from <em>local shops.</em></h2></div><p>Claim a voucher now, then choose it in your bag at COD checkout.</p></div>
                <div class="voucher-grid">
                    @foreach($claimableVouchers as $voucher)
                        <article class="voucher-card">
                            <p class="voucher-store">{{ $voucher->seller->name }}</p>
                            <h3>{{ $voucher->discount_type === 'fixed' ? '₱'.number_format($voucher->discount_minor / 100, 2).' off' : $voucher->discount_percent.'% off' }}</h3>
                            <p>Min. spend ₱{{ number_format($voucher->minimum_spend_minor / 100, 2) }} · Ends {{ $voucher->ends_at->format('M j, g:i A') }}</p>
                            @auth
                                @if(auth()->user()->isBuyer())
                                    @if($claimedVoucherIds->contains($voucher->id))
                                        <span class="voucher-claimed">Saved to your vouchers</span>
                                    @else
                                        <form method="post" action="{{ route('buyer.vouchers.claim', $voucher) }}">@csrf<button type="submit">Claim {{ $voucher->code }}</button></form>
                                    @endif
                                @else
                                    <a href="{{ route('shop') }}">Buyer accounts can claim vouchers</a>
                                @endif
                            @else
                                <a href="{{ route('login') }}">Sign in to claim</a>
                            @endauth
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if($activeBundles->isNotEmpty())
            <section class="container voucher-section" aria-labelledby="bundle-title">
                <div class="section-heading"><div><span class="eyebrow">MORE TO LOVE</span><h2 id="bundle-title">Bundle offers from <em>local shops.</em></h2></div><p>Add the qualifying items to your bag and we’ll apply the best eligible bundle from each shop automatically.</p></div>
                <div class="voucher-grid">
                    @foreach($activeBundles as $bundle)
                        <article class="voucher-card">
                            <p class="voucher-store">{{ $bundle->seller->name }}</p>
                            <h3>{{ $bundle->name }}</h3>
                            <p>{{ $bundle->type === 'percentage_bundle' ? $bundle->discount_percent.'% off when you add '.$bundle->buy_quantity.' qualifying items' : 'Buy '.$bundle->buy_quantity.', get '.$bundle->get_quantity.' reward item'.($bundle->get_quantity === 1 ? '' : 's').' free' }}</p>
                            <p class="mt-2 text-xs">Qualifying: {{ $bundle->qualifierProducts->pluck('name')->join(', ') }}</p>
                            <a href="{{ route('shops.show', $bundle->seller) }}">Shop this offer</a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="container recommended-section" id="recommended">
            <div class="section-heading"><div><span class="eyebrow">PICKED FOR YOU</span><h2>Discover your <em>next favorite.</em></h2></div>@if($products->hasPages())<a class="section-link" href="{{ $products->nextPageUrl() ?? route('shop') }}">More products ↗</a>@endif</div>
            <div class="product-grid recommended-grid">
                @forelse($products as $product)
                    @include('buyer.partials.home-product-card', ['product' => $product])
                @empty
                    <div class="empty-state product-empty-state"><h3>New finds are coming soon.</h3><p>Try another search or check back after sellers publish their products.</p></div>
                @endforelse
            </div>
            <div class="center-action"><a href="{{ route('shop') }}" class="outline-button">Explore all products ↗</a></div>
            @if($products->hasPages())<div class="marketplace-pagination">{{ $products->onEachSide(1)->links() }}</div>@endif
        </section>

        <section class="container benefits-section" aria-label="Lumear benefits">
            <div class="benefits-grid">
                <div class="benefit"><span>♧</span><div><strong>Easy shopping</strong><small>Find everyday products in one place</small></div></div>
                <div class="benefit"><span>◇</span><div><strong>Cash on delivery</strong><small>Pay when your order arrives</small></div></div>
                <div class="benefit"><span>▣</span><div><strong>Order tracking</strong><small>Follow the status of your purchase</small></div></div>
                <div class="benefit"><span>☏</span><div><strong>Seller messages</strong><small>Ask before you place an order</small></div></div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/buyer/flash-deals.js') }}?v=2" defer></script>
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buyer/home.css') }}?v=11">
    <link rel="stylesheet" href="{{ asset('css/buyer/flash-pricing.css') }}?v=1">
@endpush

@push('scripts')
    <script src="{{ asset('js/buyer/home-carousel.js') }}?v=1" defer></script>
    <script src="{{ asset('js/buyer/home-product-navigation.js') }}?v=2" defer></script>
@endpush
