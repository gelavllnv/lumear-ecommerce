@extends('layouts.buyer')

@section('title', 'Lumear — Shop the good stuff')

@section('content')

@php
    $categories = \App\Support\BuyerCategories::all();

    $banners = [
        ['title' => 'Home refresh, up to 40% off', 'sub' => 'Rattan, ceramic & linen picks for the season', 'emoji' => '🪴'],
        ['title' => 'Payday Flash Sale', 'sub' => 'Electronics, apparel & more — today only', 'emoji' => '⚡'],
        ['title' => 'New: Jewelry & Watches', 'sub' => 'Everyday gold, minimal silhouettes', 'emoji' => '💍'],
    ];

    $flashDeals = [
        ['name' => 'Ceramic Pour-Over Coffee Set', 'price' => '649', 'old_price' => '980', 'discount' => 34, 'emoji' => '☕', 'bg' => 'sand-400/25'],
        ['name' => 'Wireless Noise-Cancel Earbuds', 'price' => '1,299', 'old_price' => '2,100', 'discount' => 38, 'emoji' => '🎧', 'bg' => 'maroon-700/10'],
        ['name' => 'Plush Orthopedic Pet Bed (M)', 'price' => '899', 'old_price' => '1,250', 'discount' => 28, 'emoji' => '🐶', 'bg' => 'gold-500/15'],
        ['name' => 'Linen Blend Wide-Leg Trousers', 'price' => '1,050', 'old_price' => '1,600', 'discount' => 34, 'emoji' => '👖', 'bg' => 'sand-400/25'],
        ['name' => 'Stainless Pour Kettle 1.2L', 'price' => '780', 'old_price' => '1,100', 'discount' => 29, 'emoji' => '🫖', 'bg' => 'maroon-700/10'],
        ['name' => 'Kids Glow-in-Dark Puzzle Set', 'price' => '420', 'old_price' => '650', 'discount' => 35, 'emoji' => '🧩', 'bg' => 'gold-500/15'],
    ];

    $recommended = [
        ['name' => 'Vintage Gold-Rim Reading Glasses', 'price' => '399', 'emoji' => '👓', 'bg' => 'sand-400/25'],
        ['name' => 'Bamboo Fiber Bath Towel Set', 'price' => '560', 'emoji' => '🧺', 'bg' => 'maroon-700/10'],
        ['name' => 'Mechanical Keyboard, Brown Switch', 'price' => '2,450', 'emoji' => '⌨️', 'bg' => 'gold-500/15'],
        ['name' => 'Freeze-Dried Mango Snack Pack', 'price' => '210', 'emoji' => '🥭', 'bg' => 'sand-400/25'],
        ['name' => 'Adjustable Dumbbell Set 20kg', 'price' => '3,100', 'emoji' => '🏋️', 'bg' => 'maroon-700/10'],
        ['name' => 'Minimalist Leather Watch Strap', 'price' => '640', 'emoji' => '⌚', 'bg' => 'gold-500/15'],
        ['name' => 'Terracotta Planter Trio', 'price' => '480', 'emoji' => '🪴', 'bg' => 'sand-400/25'],
        ['name' => 'Paperback Bestseller Bundle x3', 'price' => '890', 'emoji' => '📖', 'bg' => 'maroon-700/10'],
        ['name' => '14K Gold-Plated Hoop Earrings', 'price' => '520', 'emoji' => '💫', 'bg' => 'gold-500/15'],
        ['name' => 'Toddler Sensory Play Mat', 'price' => '990', 'emoji' => '🧸', 'bg' => 'sand-400/25'],
        ['name' => 'Ergonomic Office Chair, Mesh', 'price' => '4,300', 'emoji' => '🪑', 'bg' => 'maroon-700/10'],
        ['name' => 'Men\'s Oxford Weave Shirt', 'price' => '780', 'emoji' => '👔', 'bg' => 'gold-500/15'],
    ];
@endphp

{{-- ============================ BANNER CAROUSEL ============================ --}}
<section class="bg-cream-100">
    <div class="max-w-7xl mx-auto px-4 md:px-6 pt-5">
        <div x-data="bannerCarousel()" x-init="start()" class="relative rounded-lg overflow-hidden h-64 md:h-80 bg-maroon-800">
            <template x-for="(b, i) in slides" :key="i">
                <div x-show="active === i"
                     x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute inset-0 flex items-center bg-gradient-to-r from-maroon-800 via-maroon-700 to-maroon-600">
                    <div class="px-8 md:px-16 max-w-lg">
                        <p class="font-display italic text-3xl md:text-5xl text-cream-50 leading-tight" x-text="b.title"></p>
                        <p class="text-cream-100/70 text-sm md:text-base mt-3" x-text="b.sub"></p>
                        <a href="#deals" class="inline-block mt-6 px-6 py-2.5 rounded-md bg-cream-50 text-maroon-800 text-sm font-bold hover:bg-cream-100 transition-colors duration-200">SHOP NOW</a>
                    </div>
                    <span class="hidden md:block absolute right-10 text-[9rem] opacity-90" x-text="b.emoji"></span>
                </div>
            </template>

            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                <template x-for="(b, i) in slides" :key="'d'+i">
                    <button @click="active = i" class="h-1.5 rounded-full transition-all duration-300" :class="active === i ? 'w-6 bg-cream-50' : 'w-1.5 bg-cream-50/40'"></button>
                </template>
            </div>
        </div>

        {{-- Quick category card overlapping the banner's bottom edge, Shopee-style --}}
        <div class="relative -mt-7 md:-mt-8 mx-2 md:mx-4">
            <div class="bg-cream-50 rounded-lg shadow-lift border border-sand-400/30 px-4 md:px-8 py-5 flex gap-5 md:gap-8 overflow-x-auto">
                @foreach ($categories as $cat)
                    <a href="{{ url('/category/'.$cat['slug']) }}" class="shrink-0 flex flex-col items-center gap-2 group w-16">
                        <span class="w-12 h-12 rounded-lg bg-sand-400/20 grid place-items-center text-2xl group-hover:bg-maroon-700 group-hover:text-cream-50 transition-colors duration-200">{{ $cat['icon'] }}</span>
                        <span class="text-[11px] text-center text-maroon-800 leading-tight">{{ $cat['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ============================ FLASH DEALS ============================ --}}
<section id="deals" x-data="flashCountdown()" x-init="tick()" class="max-w-7xl mx-auto px-4 md:px-6 mt-10">
    <div class="bg-maroon-800 rounded-lg px-6 py-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div class="flex items-center gap-4">
                <h2 class="font-display italic text-2xl md:text-3xl text-cream-50">Flash Deals</h2>
                <div class="flex items-center gap-1">
                    <span class="w-8 h-8 rounded bg-cream-50 text-maroon-800 grid place-items-center font-bold text-sm" x-text="h"></span>
                    <span class="text-cream-50 font-bold">:</span>
                    <span class="w-8 h-8 rounded bg-cream-50 text-maroon-800 grid place-items-center font-bold text-sm" x-text="m"></span>
                    <span class="text-cream-50 font-bold">:</span>
                    <span class="w-8 h-8 rounded bg-cream-50 text-maroon-800 grid place-items-center font-bold text-sm" x-text="s"></span>
                </div>
            </div>
            <a href="{{ url('/deals') }}" class="text-cream-100/70 text-sm font-semibold link-grow hover:text-cream-50">See all deals →</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 md:gap-4">
            @foreach ($flashDeals as $product)
                @include('partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ RECOMMENDED ============================ --}}
<section class="max-w-7xl mx-auto px-4 md:px-6 mt-12 mb-16">
    <div class="border-l-4 border-maroon-700 pl-3 mb-6">
        <h2 class="text-xl font-bold text-maroon-900 uppercase tracking-wide">Recommended For You</h2>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 md:gap-4">
        @foreach ($recommended as $product)
            @include('partials.product-card', ['product' => $product])
        @endforeach
    </div>
</section>

@endsection

@push('scripts')
<script>
    function bannerCarousel() {
        return {
            active: 0,
            slides: {{ Illuminate\Support\Js::from($banners) }},
            start() { setInterval(() => { this.active = (this.active + 1) % this.slides.length }, 4500) }
        }
    }

    function flashCountdown() {
        return {
            h: '00', m: '00', s: '00',
            endsAt: Date.now() + (3 * 3600 + 24 * 60 + 12) * 1000,
            tick() {
                const update = () => {
                    let diff = Math.max(0, this.endsAt - Date.now());
                    this.h = String(Math.floor(diff / 3600000)).padStart(2, '0');
                    this.m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
                    this.s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
                };
                update();
                setInterval(update, 1000);
            }
        }
    }
</script>
@endpush