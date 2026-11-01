@extends('layouts.landing')

@section('title', 'Lumear — Discover more, beautifully')

@section('content')
@php
    $categories = [
        ['name' => 'Home living', 'image' => 'story.jpg', 'copy' => 'Make room for the everyday.'],
        ['name' => 'Style finds', 'image' => 'cardigan.jpg', 'copy' => 'Wear what feels like you.'],
        ['name' => 'Slow rituals', 'image' => 'skincare.jpg', 'copy' => 'Small pauses, well kept.'],
        ['name' => 'Table stories', 'image' => 'ceramics.jpg', 'copy' => 'Gather around good things.'],
    ];

    $products = [
        ['name' => 'Cloud Wool Cardigan', 'price' => '2,680', 'image' => 'cardigan.jpg', 'tag' => 'Just in'],
        ['name' => 'Solenne Leather Bag', 'price' => '4,200', 'image' => 'bag.jpg', 'tag' => 'Editor’s pick'],
        ['name' => 'Form Kitchen Set', 'price' => '2,950', 'image' => 'kitchen.jpg', 'tag' => 'For the home'],
        ['name' => 'Arden Floor Lamp', 'price' => '7,850', 'image' => 'lamp.jpg', 'tag' => 'New arrival'],
    ];
@endphp

<div class="announcement-bar" role="status">
    <span>Philippine marketplace</span>
    <span aria-hidden="true">✦</span>
    <span>Thoughtful local finds</span>
    <span aria-hidden="true">✦</span>
    <span>Cash on delivery available</span>
</div>

<header class="landing-header">
    <nav class="landing-nav" aria-label="Main navigation">
        <a href="{{ route('landing') }}" class="brand" aria-label="Lumear home">
            <span class="brand-mark" aria-hidden="true">L</span>
            <span>Lumear</span>
        </a>

        <div class="nav-links" aria-label="Explore Lumear">
            <a href="#discover">Discover</a>
            <a href="#featured">Featured</a>
            <a href="#our-story">Our story</a>
        </div>

        <div class="nav-actions">
            <a href="{{ route('login') }}" class="nav-login">Sign in</a>
            <a href="{{ route('register') }}" class="button button-dark">Join Lumear <span aria-hidden="true">↗</span></a>
        </div>
    </nav>
</header>

<main>
    <section class="hero-carousel" data-hero-carousel aria-roledescription="carousel" aria-label="Lumear featured collections">
        <article class="hero-slide is-active" data-hero-slide aria-hidden="false">
            <video class="hero-video" muted loop playsinline preload="metadata" poster="{{ asset('images/landing/hero.jpg') }}">
                <source src="{{ asset('media/landing/couch.mp4') }}" type="video/mp4">
            </video>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <p class="eyebrow light">A more considered home</p>
                <h1>Everyday pieces,<br><em>made memorable.</em></h1>
                <p class="hero-copy">Find the objects, makers, and small details that make a space feel unmistakably yours.</p>
                <div class="hero-actions">
                    <a href="{{ route('shop') }}" class="button button-light">Shop home finds <span aria-hidden="true">→</span></a>
                    <a href="#our-story" class="text-link light">Why Lumear <span aria-hidden="true">↗</span></a>
                </div>
            </div>
            <p class="hero-index" aria-hidden="true"><span>01</span> / 03</p>
        </article>

        <article class="hero-slide" data-hero-slide aria-hidden="true">
            <video class="hero-video" muted loop playsinline preload="metadata" poster="{{ asset('images/landing/kitchen.jpg') }}">
                <source src="{{ asset('media/landing/air-purifier.mp4') }}" type="video/mp4">
            </video>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <p class="eyebrow light">A breath of fresh air</p>
                <h2>Make space for<br><em>better living.</em></h2>
                <p class="hero-copy">Designed for the daily rhythm—useful essentials with a little more care in every detail.</p>
                <div class="hero-actions">
                    <a href="{{ route('shop') }}" class="button button-light">Explore essentials <span aria-hidden="true">→</span></a>
                    <a href="#featured" class="text-link light">See what’s new <span aria-hidden="true">↗</span></a>
                </div>
            </div>
            <p class="hero-index" aria-hidden="true"><span>02</span> / 03</p>
        </article>

        <article class="hero-slide" data-hero-slide aria-hidden="true">
            <video class="hero-video" muted loop playsinline preload="metadata" poster="{{ asset('images/landing/coffee.jpg') }}">
                <source src="{{ asset('media/landing/bottles.mp4') }}" type="video/mp4">
            </video>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <p class="eyebrow light">Small rituals, every day</p>
                <h2>Bring color to<br><em>the in-between.</em></h2>
                <p class="hero-copy">From on-the-go favourites to thoughtful gifts, meet the pieces that brighten the ordinary.</p>
                <div class="hero-actions">
                    <a href="{{ route('shop') }}" class="button button-light">Find your favourite <span aria-hidden="true">→</span></a>
                    <a href="#discover" class="text-link light">Browse by mood <span aria-hidden="true">↗</span></a>
                </div>
            </div>
            <p class="hero-index" aria-hidden="true"><span>03</span> / 03</p>
        </article>

        <div class="carousel-controls">
            <button type="button" class="carousel-arrow" data-carousel-prev aria-label="Show previous feature">←</button>
            <div class="carousel-dots" role="tablist" aria-label="Featured collections">
                <button type="button" class="carousel-dot is-active" data-carousel-dot="0" role="tab" aria-selected="true" aria-label="Show feature 1"></button>
                <button type="button" class="carousel-dot" data-carousel-dot="1" role="tab" aria-selected="false" aria-label="Show feature 2"></button>
                <button type="button" class="carousel-dot" data-carousel-dot="2" role="tab" aria-selected="false" aria-label="Show feature 3"></button>
            </div>
            <button type="button" class="carousel-arrow" data-carousel-next aria-label="Show next feature">→</button>
        </div>
    </section>

    <section class="intro-section" id="discover">
        <div class="section-heading split-heading">
            <div>
                <p class="eyebrow">Made for how you live</p>
                <h2>Discover a marketplace<br>with <em>more feeling.</em></h2>
            </div>
            <p>Not just more things. Find locally loved pieces, emerging sellers, and dependable essentials chosen for the life you are building.</p>
        </div>

        <div class="mood-grid">
            @foreach ($categories as $index => $category)
                <a href="{{ route('shop') }}" class="mood-card {{ $index === 0 ? 'mood-card-wide' : '' }}">
                    <img src="{{ asset('images/landing/'.$category['image']) }}" alt="{{ $category['name'] }} collection">
                    <div class="mood-card-content">
                        <span class="eyebrow light">0{{ $index + 1 }}</span>
                        <h3>{{ $category['name'] }}</h3>
                        <p>{{ $category['copy'] }}</p>
                        <span class="round-link" aria-hidden="true">↗</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="feature-ribbon" aria-label="Lumear benefits">
        <p><span>01</span> Secure COD checkout</p>
        <p><span>02</span> Local sellers &amp; honest finds</p>
        <p><span>03</span> Delivery updates that keep up</p>
    </section>

    <section class="featured-section" id="featured">
        <div class="section-heading section-heading-row">
            <div>
                <p class="eyebrow">The Lumear edit</p>
                <h2>Worth a second<br><em>look.</em></h2>
            </div>
            <a href="{{ route('shop') }}" class="text-link">View the marketplace <span aria-hidden="true">→</span></a>
        </div>

        <div class="product-grid">
            @foreach ($products as $product)
                <a href="{{ route('shop') }}" class="product-card">
                    <div class="product-image-wrap">
                        <img src="{{ asset('images/landing/'.$product['image']) }}" alt="{{ $product['name'] }}">
                        <span class="product-tag">{{ $product['tag'] }}</span>
                        <span class="product-action" aria-hidden="true">+</span>
                    </div>
                    <div class="product-info">
                        <div>
                            <p class="product-name">{{ $product['name'] }}</p>
                            <p class="product-source">Marketplace find</p>
                        </div>
                        <p class="product-price">₱{{ $product['price'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="story-section" id="our-story">
        <div class="story-photo">
            <img src="{{ asset('images/landing/story.jpg') }}" alt="A wall of collected art and everyday objects">
        </div>
        <div class="story-content">
            <p class="eyebrow">The Lumear point of view</p>
            <h2>Good finds change<br>the feel of a <em>day.</em></h2>
            <p>We created Lumear for the joy of noticing: a piece with character, a seller with care, an everyday essential that works a little harder for you.</p>
            <a href="{{ route('register') }}" class="button button-dark">Create an account <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section class="seller-callout">
        <p class="eyebrow light">Sell with Lumear</p>
        <h2>Have something good<br>to put into the <em>world?</em></h2>
        <a href="{{ route('register', ['role' => 'seller']) }}" class="button button-gold">Open your store <span aria-hidden="true">↗</span></a>
    </section>
</main>

<footer class="landing-footer">
    <div class="footer-main">
        <a href="{{ route('landing') }}" class="brand footer-brand"><span class="brand-mark" aria-hidden="true">L</span><span>Lumear</span></a>
        <p>Good things, thoughtfully found.<br>Made for everyday life in the Philippines.</p>
        <div class="footer-links">
            <a href="{{ route('shop') }}">Marketplace</a>
            <a href="{{ route('login') }}">Sign in</a>
            <a href="{{ route('register') }}">Create account</a>
        </div>
    </div>
    <div class="footer-bottom"><span>© {{ date('Y') }} Lumear</span><span>Local finds · COD checkout · Thoughtful shopping</span></div>
</footer>
@endsection
