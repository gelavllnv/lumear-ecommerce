
@extends('layouts.buyer')

@section('title', 'LUMEAR — Discover Your Everyday')

@section('content')

@php
    $categories = [
        ['name' => 'Pet Supplies', 'icon' => '🐾', 'slug' => 'pet-supplies'],
        ['name' => 'Kids & Baby', 'icon' => '🧸', 'slug' => 'kids-baby'],
        ['name' => 'Electronics', 'icon' => '🎧', 'slug' => 'electronics-gadgets'],
        ['name' => 'Home & Garden', 'icon' => '🪴', 'slug' => 'home-garden'],
        ['name' => "Women's Fashion", 'icon' => '👗', 'slug' => 'womens-apparel'],
        ['name' => "Men's Fashion", 'icon' => '👔', 'slug' => 'mens-apparel'],
        ['name' => 'Sports', 'icon' => '🏸', 'slug' => 'sports-outdoors'],
        ['name' => 'Beauty', 'icon' => '💄', 'slug' => 'health-beauty'],
        ['name' => 'Books & Media', 'icon' => '📚', 'slug' => 'books-media'],
        ['name' => 'Food', 'icon' => '☕', 'slug' => 'food-gourmet'],
        ['name' => 'Furniture', 'icon' => '🪑', 'slug' => 'furniture-office-equipment'],
        ['name' => 'Accessories', 'icon' => '⌚', 'slug' => 'jewelry-watches'],
    ];

    $products = [
        ['id'=>'p01','name'=>'Ceramic Pour-Over Coffee Set','seller'=>'Casa Living','price'=>649,'old'=>980,'rating'=>4.9,'badge'=>'-34%','stock'=>18,'image'=>'photo-1495474472287-4d71bcdd2085'],
        ['id'=>'p02','name'=>'Wireless Noise-Cancelling Headphones','seller'=>'Tech Avenue','price'=>1299,'old'=>2100,'rating'=>4.8,'badge'=>'-38%','stock'=>24,'image'=>'photo-1505740420928-5e560c06d30e'],
        ['id'=>'p03','name'=>'Cozy Plush Teddy Bear','seller'=>'Little Joys','price'=>899,'old'=>1250,'rating'=>4.9,'badge'=>'-28%','stock'=>12,'image'=>'photo-1559454403-b8fb88521f11'],
        ['id'=>'p04','name'=>'Classic Everyday Denim Jeans','seller'=>'Urban Thread','price'=>1050,'old'=>1600,'rating'=>4.8,'badge'=>'-34%','stock'=>20,'image'=>'photo-1542272604-787c3835535d'],
        ['id'=>'p05','name'=>'Modern Glass Teapot Set','seller'=>'Kitchen & Co.','price'=>780,'old'=>1100,'rating'=>4.8,'badge'=>'-29%','stock'=>14,'image'=>'photo-1511920170033-f8396924c348'],
        ['id'=>'p06','name'=>'Minimalist Desk Accessories','seller'=>'Workspace','price'=>420,'old'=>650,'rating'=>4.7,'badge'=>'-35%','stock'=>36,'image'=>'photo-1498050108023-c5249f4df085'],
        ['id'=>'p07','name'=>'Vintage Gold-Rim Sunglasses','seller'=>'Soleil Studio','price'=>399,'old'=>null,'rating'=>4.8,'badge'=>'Popular','stock'=>22,'image'=>'photo-1511499767150-a48a237f0083'],
        ['id'=>'p08','name'=>'Handwoven Natural Basket','seller'=>'Halo Home','price'=>560,'old'=>null,'rating'=>4.9,'badge'=>'Local Find','stock'=>15,'image'=>'photo-1494438639946-1ebd1d20bf85'],
        ['id'=>'p09','name'=>'Mechanical Keyboard','seller'=>'KeySpace','price'=>2450,'old'=>null,'rating'=>4.9,'badge'=>'Trending','stock'=>11,'image'=>'photo-1587829741301-dc798b83add3'],
        ['id'=>'p10','name'=>'Fresh Citrus Scented Candle','seller'=>'Slow Sundays','price'=>310,'old'=>null,'rating'=>4.7,'badge'=>'New','stock'=>31,'image'=>'photo-1603006905003-be475563bc59'],
        ['id'=>'p11','name'=>'Adjustable Fitness Dumbbell','seller'=>'Active Fit','price'=>1390,'old'=>null,'rating'=>4.8,'badge'=>'Best Seller','stock'=>17,'image'=>'photo-1517836357463-d25dfeac3438'],
        ['id'=>'p12','name'=>'Classic Minimalist Wristwatch','seller'=>'Timepiece','price'=>640,'old'=>null,'rating'=>4.8,'badge'=>'Popular','stock'=>19,'image'=>'photo-1523275335684-37898b6baf30'],
        ['id'=>'p13','name'=>'Ceramic Planter with Greenery','seller'=>'Green Living','price'=>480,'old'=>null,'rating'=>4.9,'badge'=>'Eco Pick','stock'=>29,'image'=>'photo-1416879595882-3373a0480b5b'],
        ['id'=>'p14','name'=>'Paperback Reading Collection','seller'=>'Chapter One','price'=>890,'old'=>null,'rating'=>4.8,'badge'=>"Editor's Pick",'stock'=>13,'image'=>'photo-1495446815901-a7297e633e8d'],
        ['id'=>'p15','name'=>'Elegant Gold Hoop Earrings','seller'=>'Luna Jewelry','price'=>520,'old'=>null,'rating'=>4.9,'badge'=>'Trending','stock'=>25,'image'=>'photo-1535632066927-ab7c9ab60908'],
        ['id'=>'p16','name'=>'Soft Plush Teddy Bear','seller'=>'Little Joys','price'=>990,'old'=>null,'rating'=>4.9,'badge'=>'Gift Idea','stock'=>18,'image'=>'photo-1559454403-b8fb88521f11'],
        ['id'=>'p17','name'=>'Modern Wooden Dining Chair','seller'=>'Oak & Home','price'=>4300,'old'=>null,'rating'=>4.8,'badge'=>'Home Favorite','stock'=>7,'image'=>'photo-1503602642458-232111445657'],
        ['id'=>'p18','name'=>'Premium Cotton Casual Shirt','seller'=>'Urban Thread','price'=>780,'old'=>null,'rating'=>4.7,'badge'=>'Everyday','stock'=>28,'image'=>'photo-1598032895397-b9472444bf93'],
    ];
@endphp

<div class="lumear-home">

    {{-- =====================================================
         HERO CAROUSEL
         ===================================================== --}}

    <section class="container hero-section">
        <div class="hero-carousel" id="heroSlider">

            <div class="hero-track" id="heroTrack">

                {{-- Slide 1: Home --}}
                <article class="hero-slide hero-home active">
                    <div class="hero-copy">
                        <span class="hero-kicker">THE HOME EDIT · 2026</span>

                        <h1>
                            Home refresh,<br>
                            <em>up to 40% off.</em>
                        </h1>

                        <p>
                            Thoughtful details to make every corner feel like yours.
                        </p>

                        <a href="{{ url('/category/home-garden') }}" class="hero-button">
                            Shop Home <span>↗</span>
                        </a>
                    </div>

                    <span class="hero-caption">Live beautifully.</span>
                </article>

                {{-- Slide 2: Fashion --}}
                <article class="hero-slide hero-fashion">
                    <div class="hero-copy">
                        <span class="hero-kicker">STYLE YOUR EVERYDAY</span>

                        <h1>
                            New season,<br>
                            <em>new favorites.</em>
                        </h1>

                        <p>
                            Discover fashion finds made for your personal style.
                        </p>

                        <a href="{{ url('/category/womens-apparel') }}" class="hero-button">
                            Shop Fashion <span>↗</span>
                        </a>
                    </div>

                    <span class="hero-caption">Find your style.</span>
                </article>

                {{-- Slide 3: Beauty --}}
                <article class="hero-slide hero-beauty">
                    <div class="hero-copy">
                        <span class="hero-kicker">THE SELF-CARE COLLECTION</span>

                        <h1>
                            Little rituals,<br>
                            <em>lovely moments.</em>
                        </h1>

                        <p>
                            Discover beauty and wellness essentials for everyday living.
                        </p>

                        <a href="{{ url('/category/health-beauty') }}" class="hero-button">
                            Explore Beauty <span>↗</span>
                        </a>
                    </div>

                    <span class="hero-caption">A moment for you.</span>
                </article>

            </div>

            {{-- Previous Slide --}}
            <button
                type="button"
                class="hero-arrow hero-prev"
                id="heroPrev"
                aria-label="Previous slide"
            >
                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2.2"
                     stroke-linecap="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
            </button>

            {{-- Next Slide --}}
            <button
                type="button"
                class="hero-arrow hero-next"
                id="heroNext"
                aria-label="Next slide"
            >
                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2.2"
                     stroke-linecap="round">
                    <path d="m9 18 6-6-6-6"/>
                </svg>
            </button>

            {{-- Pagination --}}
            <div class="hero-bottom">
                <div class="hero-dots" id="heroPagination">
                    <button type="button" class="active" aria-label="Go to slide 1"></button>
                    <button type="button" aria-label="Go to slide 2"></button>
                    <button type="button" aria-label="Go to slide 3"></button>
                </div>

                <span id="heroSlideCounter">01 / 03</span>
            </div>

            {{-- Progress Bar --}}
            <div class="hero-progress">
                <span id="heroProgress"></span>
            </div>

        </div>
    </section>


    {{-- =====================================================
         CATEGORY STRIP
         ===================================================== --}}

    <section class="container category-section" aria-label="Shop by category">

        <div class="category-strip">

            @foreach($categories as $category)

                <a
                    class="category-tile"
                    href="{{ url('/category/'.$category['slug']) }}"
                >
                    <span class="category-icon">
                        {{ $category['icon'] }}
                    </span>

                    <span>
                        {{ $category['name'] }}
                    </span>
                </a>

            @endforeach

        </div>

    </section>


    {{-- =====================================================
         EDITORIAL COLLECTIONS
         ===================================================== --}}

    <section class="container collection-section" id="collections">

        <div class="section-heading">
            <div>
                <span class="eyebrow">
                    DISCOVER SOMETHING NEW
                </span>

                <h2>
                    Curated for <em>your everyday.</em>
                </h2>
            </div>

            <p>Little discoveries, big possibilities.</p>
        </div>

        <div class="promo-grid">

            {{-- Fashion --}}
            <a
                href="{{ url('/category/womens-apparel') }}"
                class="promo-card promo-fashion"
            >
                <div class="promo-copy">
                    <span>NEW ARRIVALS</span>

                    <h3>
                        Trendy finds<br>
                        <em>for less.</em>
                    </h3>

                    <p>Refresh your wardrobe.</p>

                    <strong>Shop Now ↗</strong>
                </div>
            </a>

            {{-- Beauty --}}
            <a
                href="{{ url('/category/health-beauty') }}"
                class="promo-card promo-beauty"
            >
                <div class="promo-copy">
                    <span>BEAUTY FAVORITES</span>

                    <h3>
                        Glow for<br>
                        <em>everyday.</em>
                    </h3>

                    <p>Make room for self-care.</p>

                    <strong>Explore ↗</strong>
                </div>
            </a>

            {{-- Workspace --}}
            <a
                href="{{ url('/category/furniture-office-equipment') }}"
                class="promo-card promo-work"
            >
                <div class="promo-copy">
                    <span>WORKSPACE EDIT</span>

                    <h3>
                        Better space,<br>
                        <em>brighter days.</em>
                    </h3>

                    <p>Make work feel better.</p>

                    <strong>Discover ↗</strong>
                </div>
            </a>

        </div>

    </section>


    {{-- =====================================================
         FLASH DEALS
         ===================================================== --}}

    <section class="container deals-section" id="flash-deals">

        <div class="deals-panel">

            <div class="deals-heading">

                <div class="deals-heading-left">

                    <div>
                        <span class="eyebrow eyebrow-light">
                            LIMITED-TIME OFFERS
                        </span>

                        <h2>Flash Deals ✦</h2>
                    </div>

                    <div class="countdown" aria-label="Demo sale countdown">
                        <span id="countHours">02</span>
                        <b>:</b>
                        <span id="countMinutes">00</span>
                        <b>:</b>
                        <span id="countSeconds">00</span>
                    </div>

                </div>

                <a href="#recommended">
                    See all deals ↗
                </a>

            </div>

            <div class="product-grid flash-grid">

                @foreach(array_slice($products, 0, 6) as $product)

                    @include('buyer.partials.home-product-card', [
                        'product' => $product
                    ])

                @endforeach

            </div>

        </div>

    </section>


    {{-- =====================================================
         RECOMMENDED PRODUCTS
         ===================================================== --}}

    <section class="container recommended-section" id="recommended">

        <div class="section-heading">

            <div>
                <span class="eyebrow">
                    PICKED JUST FOR YOU
                </span>

                <h2>
                    Discover your <em>next favorite.</em>
                </h2>
            </div>

            <a class="section-link" href="{{ url('/search') }}">
                Explore More ↗
            </a>

        </div>

        <div class="product-grid recommended-grid">

            @foreach(array_slice($products, 6) as $product)

                @include('buyer.partials.home-product-card', [
                    'product' => $product
                ])

            @endforeach

        </div>

        <div class="center-action">
            <a href="{{ url('/search') }}" class="outline-button">
                Explore All Products ↗
            </a>
        </div>

    </section>


    {{-- =====================================================
         SHOPPING BENEFITS
         ===================================================== --}}

    <section class="container benefits-section">

        <div class="benefits-grid">

            <div class="benefit">
                <span>♧</span>
                <div>
                    <strong>Convenient Shopping</strong>
                    <small>Find what you love in one place</small>
                </div>
            </div>

            <div class="benefit">
                <span>◇</span>
                <div>
                    <strong>Flexible Payments</strong>
                    <small>Options available at checkout</small>
                </div>
            </div>

            <div class="benefit">
                <span>▣</span>
                <div>
                    <strong>Order Tracking</strong>
                    <small>Follow your purchase journey</small>
                </div>
            </div>

            <div class="benefit">
                <span>☏</span>
                <div>
                    <strong>Customer Support</strong>
                    <small>We're here to help</small>
                </div>
            </div>

        </div>

    </section>


    {{-- =====================================================
         SAVED PRODUCTS PANEL
         ===================================================== --}}

    <div class="floating-panel" id="wishlistPanel" hidden>

        <div class="panel-heading">
            <strong>Saved Products</strong>

            <button
                type="button"
                id="wishlistClose"
                aria-label="Close saved products"
            >
                ×
            </button>
        </div>

        <div id="wishlistPanelContent"></div>

    </div>


    {{-- =====================================================
         NOTIFICATIONS PANEL
         ===================================================== --}}

    <div class="floating-panel" id="notificationPanel" hidden>

        <div class="panel-heading">
            <strong>Notifications</strong>

            <button
                type="button"
                id="notificationClose"
                aria-label="Close notifications"
            >
                ×
            </button>
        </div>

        <p>
            Your notifications will appear here when the backend is connected.
        </p>

    </div>


    {{-- =====================================================
         PRODUCT QUICK VIEW
         ===================================================== --}}

    <div class="modal-backdrop" id="quickViewBackdrop" hidden>

        <div
            class="quick-view-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="quickViewTitle"
        >

            <button
                class="modal-close"
                id="quickViewClose"
                type="button"
                aria-label="Close product preview"
            >
                ×
            </button>

            <div class="quick-view-image">
                <img id="quickViewImage" src="" alt="">
            </div>

            <div class="quick-view-info">

                <span class="eyebrow">
                    PRODUCT PREVIEW
                </span>

                <h2 id="quickViewTitle"></h2>

                <p id="quickViewSeller"></p>

                <strong id="quickViewPrice"></strong>

                <small id="quickViewStock"></small>

                <button
                    class="primary-button"
                    id="quickViewAddCart"
                    type="button"
                >
                    Add to Cart
                </button>

            </div>

        </div>

    </div>


    {{-- =====================================================
         TOAST NOTIFICATION
         ===================================================== --}}

    <div
        class="buyer-toast"
        id="buyerToast"
        role="status"
        aria-live="polite"
    ></div>

</div>

@endsection


{{-- =====================================================
     HOMEPAGE CSS
     ===================================================== --}}

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/buyer/home.css') }}?v=8"
    >
@endpush


{{-- =====================================================
     HOMEPAGE JAVASCRIPT
     ===================================================== --}}

@push('scripts')
    <script
        src="{{ asset('js/buyer/home.js') }}?v=8"
        defer
    ></script>

    <script src="{{ asset('js/buyer/home-product-navigation.js') }}?v=1" defer></script>
@endpush

