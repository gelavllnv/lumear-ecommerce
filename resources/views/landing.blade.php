@extends('layouts.landing')

@section('title', 'Lumear - Things made to be kept')

@section('content')
@php
    $products = [
        ['name' => 'Cloud Wool Cardigan', 'price' => '2,680', 'image' => 'cardigan.jpg', 'tag' => 'Fashion'],
        ['name' => 'Solenne Leather Bag', 'price' => '4,200', 'image' => 'bag.jpg', 'tag' => 'New'],
        ['name' => 'Form Kitchen Set', 'price' => '2,950', 'image' => 'kitchen.jpg', 'tag' => 'Appliances'],
        ['name' => 'Arden Floor Lamp', 'price' => '7,850', 'image' => 'lamp.jpg', 'tag' => 'Home & decor'],
        ['name' => 'Ritual Skin Set', 'price' => '6,480', 'image' => 'skincare.jpg', 'tag' => 'Wellness'],
        ['name' => 'Cara Ceramic Vessel', 'price' => '4,280', 'image' => 'ceramics.jpg', 'tag' => 'Home & decor'],
        ['name' => 'Sol Shade Frames', 'price' => '3,950', 'image' => 'sunglasses.jpg', 'tag' => 'Accessories'],
        ['name' => 'Mira Espresso Maker', 'price' => '1,750', 'image' => 'coffee.jpg', 'tag' => 'Appliances'],
    ];
@endphp

<header class="absolute inset-x-0 top-0 z-20 text-white">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
        <a href="{{ route('landing') }}" class="font-display text-2xl italic tracking-tight" aria-label="Lumear home">Lumear</a>
        <div class="hidden items-center gap-7 text-xs font-medium md:flex">
            <a href="#collection" class="transition hover:text-sand">Collection</a>
            <a href="#story" class="transition hover:text-sand">Our story</a>
            <a href="{{ route('shop') }}" class="transition hover:text-sand">Marketplace</a>
        </div>
        <div class="flex items-center gap-4 text-xs font-semibold">
            <a href="{{ route('login') }}" class="hidden sm:inline hover:text-sand">Log in</a>
            <a href="{{ route('shop') }}" class="border border-white/70 bg-wine px-4 py-2.5 transition hover:bg-white hover:text-ink">Shop now</a>
        </div>
    </nav>
</header>

<main>
    <section class="relative isolate min-h-[650px] overflow-hidden bg-ink sm:min-h-[730px]">
        <img src="{{ asset('images/landing/hero.jpg') }}" alt="A carefully curated rail of clothing" class="absolute inset-0 -z-20 h-full w-full object-cover object-right">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#340b16]/95 via-[#4e1825]/72 to-transparent"></div>
        <div class="mx-auto flex min-h-[650px] max-w-7xl items-center px-5 pb-16 pt-28 sm:min-h-[730px] sm:px-8 lg:px-12">
            <div class="max-w-xl text-white">
                <p class="mb-5 text-[10px] font-bold uppercase tracking-[0.24em] text-sand">Since 2026 - thoughtfully curated</p>
                <h1 class="font-display text-5xl leading-[.9] tracking-tight sm:text-7xl">Things made<br><em class="text-[#e6c78f]">to be kept.</em></h1>
                <p class="mt-7 max-w-sm text-sm leading-6 text-white/80">Lumear brings together objects of quiet beauty - pieces chosen for craftsmanship, longevity, and the feeling they add to everyday life.</p>
                <div class="mt-8 flex items-center gap-5">
                    <a href="{{ route('shop') }}" class="bg-wine px-5 py-3 text-xs font-bold uppercase tracking-wide transition hover:bg-white hover:text-ink">Explore the collection</a>
                    <a href="#story" class="text-xs font-semibold underline underline-offset-4 transition hover:text-sand">Our story</a>
                </div>
            </div>
        </div>
        <a href="#collection" class="absolute bottom-7 left-5 text-[10px] font-bold uppercase tracking-[0.2em] text-white/75 sm:left-8 lg:left-12">Scroll to explore ↓</a>
    </section>

    <div class="bg-wine py-3 text-center text-[10px] font-bold uppercase tracking-[0.16em] text-white">
        New arrivals every Friday <span class="mx-5 text-sand">✦</span> 30-day returns, no questions <span class="mx-5 text-sand">✦</span> Sustainably sourced
    </div>

    <section id="collection" class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-12 lg:py-28">
        <div class="mb-10 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
                <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.18em] text-wine">Featured products</p>
                <h2 class="font-display text-4xl leading-none sm:text-5xl">Curated for<br><em class="text-wine">quiet luxury.</em></h2>
            </div>
            <a href="{{ route('shop') }}" class="text-xs font-bold uppercase tracking-wide text-wine underline underline-offset-4">Browse the full marketplace →</a>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
            @foreach ($products as $product)
                <a href="{{ url('/product/'.Str::slug($product['name'])) }}" class="product-card group block">
                    <div class="relative aspect-[4/5] overflow-hidden bg-sand/30">
                        <img src="{{ asset('images/landing/'.$product['image']) }}" alt="{{ $product['name'] }}" class="product-image h-full w-full object-cover">
                        <span class="absolute left-3 top-3 bg-paper/95 px-2 py-1 text-[8px] font-bold uppercase tracking-wider text-ink">{{ $product['tag'] }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-3 pt-3">
                        <h3 class="text-xs font-medium leading-5 sm:text-sm">{{ $product['name'] }}</h3>
                        <span class="shrink-0 text-xs text-ink/65">₱{{ $product['price'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section id="story" class="grid bg-[#ead8b5] md:grid-cols-2">
        <div class="min-h-80 overflow-hidden md:min-h-[520px]">
            <img src="{{ asset('images/landing/story.jpg') }}" alt="A wall of collected art and objects" class="h-full w-full object-cover">
        </div>
        <div class="flex items-center px-8 py-20 sm:px-16 lg:px-24">
            <div class="max-w-md">
                <p class="mb-4 text-[10px] font-bold uppercase tracking-[0.18em] text-wine">Our point of view</p>
                <h2 class="font-display text-4xl leading-tight sm:text-5xl">Objects chosen for<br>the stories they’ll<br><em class="text-wine">gather over time.</em></h2>
                <p class="mt-6 text-sm leading-6 text-ink/70">We believe the things in your home should earn their place. Discover a more thoughtful way to shop with Lumear.</p>
                <a href="{{ route('shop') }}" class="mt-8 inline-block border-b border-ink pb-1 text-xs font-bold uppercase tracking-wide">Discover the marketplace →</a>
            </div>
        </div>
    </section>
</main>

<footer class="bg-[#350a14] text-white/70">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-8 md:grid-cols-2 lg:px-12">
        <div>
            <p class="mb-4 text-[10px] font-bold uppercase tracking-[0.18em] text-sand">A little note, now and then</p>
            <h2 class="font-display text-4xl leading-none text-white sm:text-5xl">For homes in the<br>making.</h2>
        </div>
        <div class="md:pt-6">
            <p class="max-w-sm text-sm leading-6">New arrivals, behind the scenes, and ideas for making the everyday feel considered.</p>
            <form class="mt-6 flex max-w-sm border-b border-white/40" action="{{ route('shop') }}">
                <label for="email" class="sr-only">Email address</label>
                <input id="email" type="email" placeholder="your@email.com" class="w-full bg-transparent py-3 text-sm text-white placeholder:text-white/45 focus:outline-none">
                <button class="text-xs font-bold text-white">Subscribe ↗</button>
            </form>
        </div>
    </div>
    <div class="border-t border-white/10 px-5 py-5 text-center text-[10px] text-white/45 sm:px-8">© {{ date('Y') }} Lumear. All rights reserved.</div>
</footer>
@endsection
