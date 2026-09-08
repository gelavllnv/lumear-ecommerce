@extends('layouts.buyer')

@section('title', ($product['name'] ?? 'Product') . ' — Lumear')

@section('content')

@php
    // Placeholder single-product data — replace with $product passed from controller.
    $product = $product ?? [
        'name' => 'Handwoven Rattan Pet Bed, Medium',
        'price' => '899', 'old_price' => '1,250', 'discount' => 28,
        'rating' => 4.8, 'reviews' => 1204, 'sold' => '3.2k',
        'colors' => ['Natural', 'Charcoal', 'Terracotta'],
        'sizes' => ['Small', 'Medium', 'Large'],
        'stock' => 47,
        'gallery' => ['🐶','🧺','🪵','📦'],
        'description' => "Hand-plaited rattan frame with a machine-washable cushion insert. Breathable, chew-resistant weave, finished with a non-slip base — built for daily napping, not just decoration.",
    ];

    $related = [
        ['name' => 'Cotton Rope Chew Toy Bundle', 'price' => '245', 'emoji' => '🦴', 'bg' => 'sand-400/25'],
        ['name' => 'Ceramic Pet Feeding Bowl Set', 'price' => '390', 'emoji' => '🥣', 'bg' => 'maroon-700/10'],
        ['name' => 'Adjustable Nylon Pet Harness', 'price' => '560', 'emoji' => '🦮', 'bg' => 'gold-500/15'],
        ['name' => 'Self-Warming Pet Blanket', 'price' => '480', 'emoji' => '🧶', 'bg' => 'sand-400/25'],
    ];
@endphp

<div x-data="productPage()" class="max-w-7xl mx-auto px-4 md:px-6 pt-8">

    {{-- Breadcrumb --}}
    <p class="text-xs text-maroon-900/50 mb-6">
        <a href="{{ url('/') }}" class="hover:text-maroon-700">Home</a> ·
        <a href="{{ url('/category/pet-supplies') }}" class="hover:text-maroon-700">Pet Supplies</a> ·
        <span class="text-maroon-900">{{ $product['name'] }}</span>
    </p>

    <div class="grid md:grid-cols-2 gap-10 lg:gap-16">

        {{-- Gallery --}}
        <div>
            <div class="aspect-square rounded-lg bg-sand-400/25 grid place-items-center text-8xl relative overflow-hidden shadow-soft">
                <template x-for="(emoji, i) in gallery" :key="i">
                    <span x-show="active === i" x-transition:enter="transition ease-out duration-300"
                          x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                          x-text="emoji" class="absolute"></span>
                </template>
            </div>
            <div class="flex gap-3 mt-4">
                <template x-for="(emoji, i) in gallery" :key="i">
                    <button @click="active = i"
                            class="w-16 h-16 rounded-xl grid place-items-center text-2xl border-2 transition-colors duration-200"
                            :class="active === i ? 'border-maroon-700 bg-sand-400/30' : 'border-sand-400/40 bg-cream-50'">
                        <span x-text="emoji"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Info --}}
        <div>
            <h1 class="font-display text-3xl md:text-4xl text-maroon-900 leading-tight">{{ $product['name'] }}</h1>

            <div class="flex items-center gap-3 mt-3 text-sm text-maroon-900/60">
                <span class="text-gold-500 font-semibold">★ {{ $product['rating'] }}</span>
                <span>({{ number_format($product['reviews']) }} ratings)</span>
                <span>·</span>
                <span>{{ $product['sold'] }} sold</span>
            </div>

            <div class="flex items-baseline gap-3 mt-5 bg-sand-400/20 rounded-lg px-5 py-4">
                <span class="font-display text-4xl text-maroon-700 font-semibold">₱{{ $product['price'] }}</span>
                @if(!empty($product['old_price']))
                    <span class="text-maroon-900/40 line-through">₱{{ $product['old_price'] }}</span>
                    <span class="bg-maroon-700 text-cream-50 text-xs font-bold px-2 py-1 rounded-md">-{{ $product['discount'] }}%</span>
                @endif
            </div>

            {{-- Color variation --}}
            <div class="mt-6">
                <p class="text-sm font-semibold text-maroon-900 mb-2.5">Color: <span class="font-normal text-maroon-900/60" x-text="color"></span></p>
                <div class="flex gap-2.5">
                    @foreach ($product['colors'] as $c)
                        <button @click="color = '{{ $c }}'"
                                class="px-4 py-2 rounded-md border text-sm transition-colors duration-200"
                                :class="color === '{{ $c }}' ? 'border-maroon-700 bg-maroon-700 text-cream-50' : 'border-sand-400/60 text-maroon-800 hover:border-maroon-700/50'">
                            {{ $c }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Size variation --}}
            <div class="mt-5">
                <p class="text-sm font-semibold text-maroon-900 mb-2.5">Size: <span class="font-normal text-maroon-900/60" x-text="size"></span></p>
                <div class="flex gap-2.5">
                    @foreach ($product['sizes'] as $s)
                        <button @click="size = '{{ $s }}'"
                                class="w-16 py-2 rounded-md border text-sm transition-colors duration-200"
                                :class="size === '{{ $s }}' ? 'border-maroon-700 bg-maroon-700 text-cream-50' : 'border-sand-400/60 text-maroon-800 hover:border-maroon-700/50'">
                            {{ $s }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Quantity --}}
            <div class="mt-6">
                <p class="text-sm font-semibold text-maroon-900 mb-2.5">Quantity</p>
                <div class="flex items-center gap-4">
                    <div class="flex items-center border border-sand-400/60 rounded-md overflow-hidden">
                        <button @click="qty = Math.max(1, qty - 1)" class="w-10 h-10 grid place-items-center hover:bg-sand-400/25 transition-colors">−</button>
                        <span class="w-12 text-center text-sm" x-text="qty"></span>
                        <button @click="qty = Math.min({{ $product['stock'] }}, qty + 1)" class="w-10 h-10 grid place-items-center hover:bg-sand-400/25 transition-colors">+</button>
                    </div>
                    <span class="text-xs text-maroon-900/50">{{ $product['stock'] }} pieces available</span>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 mt-8">
                <button class="flex-1 py-4 rounded-md border-2 border-maroon-700 text-maroon-700 font-bold text-sm tracking-wide hover:bg-maroon-700/5 transition-colors duration-200">
                    ADD TO CART
                </button>
                <button class="flex-1 py-4 rounded-md bg-maroon-700 text-cream-50 font-bold text-sm tracking-wide hover:bg-maroon-600 hover:shadow-lift transition-all duration-200">
                    BUY NOW
                </button>
            </div>
        </div>
    </div>

    {{-- Tabs: description / specs / reviews --}}
    <div class="mt-16 md:mt-20" x-data="{ tab: 'desc' }">
        <div class="flex gap-8 border-b border-sand-400/50">
            <button @click="tab = 'desc'" class="pb-3.5 text-sm font-semibold border-b-2 transition-colors duration-200"
                    :class="tab === 'desc' ? 'border-maroon-700 text-maroon-900' : 'border-transparent text-maroon-900/40'">Description</button>
            <button @click="tab = 'specs'" class="pb-3.5 text-sm font-semibold border-b-2 transition-colors duration-200"
                    :class="tab === 'specs' ? 'border-maroon-700 text-maroon-900' : 'border-transparent text-maroon-900/40'">Specifications</button>
            <button @click="tab = 'reviews'" class="pb-3.5 text-sm font-semibold border-b-2 transition-colors duration-200"
                    :class="tab === 'reviews' ? 'border-maroon-700 text-maroon-900' : 'border-transparent text-maroon-900/40'">Reviews ({{ $product['reviews'] }})</button>
        </div>

        <div class="py-8 max-w-2xl">
            <div x-show="tab === 'desc'" x-transition.opacity>
                <p class="text-maroon-900/70 leading-relaxed">{{ $product['description'] }}</p>
            </div>
            <div x-show="tab === 'specs'" x-transition.opacity x-cloak class="space-y-2 text-sm">
                @foreach (['Material' => 'Rattan, cotton-blend cushion', 'Weight' => '1.4 kg', 'Care' => 'Wipe frame, machine-wash cushion cover', 'Origin' => 'Locally sourced'] as $k => $v)
                    <div class="flex justify-between border-b border-sand-400/30 py-2.5">
                        <span class="text-maroon-900/50">{{ $k }}</span><span class="text-maroon-900 font-medium">{{ $v }}</span>
                    </div>
                @endforeach
            </div>
            <div x-show="tab === 'reviews'" x-transition.opacity x-cloak class="space-y-5">
                @foreach ([['name'=>'A. Reyes','stars'=>5,'text'=>'Sturdy and looks great in the living room, my cat approves.'],['name'=>'J. Santos','stars'=>4,'text'=>'Good quality, cushion could be a bit thicker.']] as $r)
                    <div class="border-b border-sand-400/30 pb-5">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-sand-400/30 grid place-items-center text-sm">🧑</span>
                            <span class="text-sm font-semibold text-maroon-900">{{ $r['name'] }}</span>
                            <span class="text-gold-500 text-xs">{{ str_repeat('★', $r['stars']) }}</span>
                        </div>
                        <p class="text-sm text-maroon-900/60 mt-2">{{ $r['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Related --}}
    <div class="mt-16 mb-20">
        <h2 class="font-display text-2xl text-maroon-900 mb-6">You may also like</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($related as $p)
                @include('partials.product-card', ['product' => $p])
            @endforeach
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function productPage() {
        return {
            gallery: {{ Illuminate\Support\Js::from($product['gallery']) }},
            active: 0,
            color: '{{ $product['colors'][0] }}',
            size: '{{ $product['sizes'][0] }}',
            qty: 1,
        }
    }
</script>
@endpush