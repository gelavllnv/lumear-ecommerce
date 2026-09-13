@extends('layouts.buyer')

@section('title', ($category['name'] ?? 'Category') . ' — Lumear')

@section('content')

@php
    // $category and $products are passed in from the route (see routes-buyer-snippet.php).
    // Fallbacks below only kick in if this view is opened without them, e.g. previewing directly.
    $category = $category ?? \App\Support\BuyerCategories::find('pet-supplies');
    $products = $products ?? \App\Support\BuyerProducts::forCategory($category['slug']);
    $allCategories = \App\Support\BuyerCategories::all();

    // Precompute a display-order rank per sort mode so the grid can reorder via CSS `order`
    // instead of re-rendering — keeps the existing product-card partial untouched and avoids
    // any client/server data duplication. "Popular" has no real relevance signal yet, so it
    // falls back to original catalog order; Top Sales / Price are genuinely sorted from the
    // actual 'sold' / 'price' values already on each product.
    $indexed      = collect($products)->values();
    $rankPopular  = $indexed->pluck('slug')->flip();
    $rankLatest   = $indexed->reverse()->values()->pluck('slug')->flip();
    $rankTopSales = $indexed->sortByDesc(fn ($p) => (int) $p['sold'])->values()->pluck('slug')->flip();
    $rankPriceAsc = $indexed->sortBy(fn ($p) => (int) $p['price'])->values()->pluck('slug')->flip();
    $rankPriceDesc = $indexed->sortByDesc(fn ($p) => (int) $p['price'])->values()->pluck('slug')->flip();
@endphp

<div x-data="categoryFilters()" class="max-w-7xl mx-auto px-4 md:px-6 pt-8 pb-20">

    {{-- Breadcrumb --}}
    <p class="text-xs text-maroon-900/50 mb-5">
        <a href="{{ url('/home') }}" class="hover:text-maroon-700">Home</a> ·
        <span class="text-maroon-900">{{ $category['name'] }}</span>
    </p>

    <div class="grid md:grid-cols-5 gap-6">

        {{-- ============ SIDEBAR ============ --}}
        <aside class="md:col-span-1 space-y-5">

            {{-- Category tree — all 12 up top, current one expands to show its subcategories
                 as filter buttons (these don't navigate, they set activeSub in place) --}}
            <div class="bg-cream-50 border border-sand-400/40 rounded-lg overflow-hidden">
                <p class="px-4 pt-4 pb-2 text-xs font-bold text-maroon-900 uppercase tracking-wide">All Categories</p>
                <nav class="text-sm pb-2">
                    @foreach ($allCategories as $cat)
                        @php $isCurrent = $cat['slug'] === $category['slug']; @endphp
                        <a href="{{ url('/category/'.$cat['slug']) }}"
                           class="block px-4 py-2 {{ $isCurrent ? 'text-maroon-700 font-bold bg-sand-400/15' : 'text-maroon-900/70 hover:text-maroon-700' }}">
                            {{ $cat['name'] }}
                        </a>
                        @if ($isCurrent)
                            <div class="pb-1.5">
                                <button @click="activeSub = 'all'"
                                        class="block w-full text-left pl-7 pr-4 py-1.5 text-[13px] transition-colors duration-150"
                                        :class="activeSub === 'all' ? 'text-maroon-700 font-semibold' : 'text-maroon-900/55 hover:text-maroon-700'">
                                    All {{ $cat['name'] }}
                                </button>
                                @foreach ($cat['subcategories'] as $sub)
                                    <button @click="activeSub = '{{ $sub['slug'] }}'"
                                            class="block w-full text-left pl-7 pr-4 py-1.5 text-[13px] transition-colors duration-150"
                                            :class="activeSub === '{{ $sub['slug'] }}' ? 'text-maroon-700 font-semibold' : 'text-maroon-900/55 hover:text-maroon-700'">
                                        {{ $sub['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </nav>
            </div>

            {{-- Price range --}}
            <div class="bg-cream-50 border border-sand-400/40 rounded-lg p-4">
                <p class="text-xs font-bold text-maroon-900 uppercase tracking-wide mb-3">Price Range</p>
                <div class="flex items-center gap-2">
                    <input type="number" x-model="minPriceInput" placeholder="Min"
                           class="w-full h-9 rounded-md bg-sand-400/15 border border-sand-400/50 px-2.5 text-xs outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">
                    <span class="text-maroon-900/30 text-xs">—</span>
                    <input type="number" x-model="maxPriceInput" placeholder="Max"
                           class="w-full h-9 rounded-md bg-sand-400/15 border border-sand-400/50 px-2.5 text-xs outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">
                </div>
                <button @click="applyPriceRange()" class="w-full mt-3 h-9 rounded-md bg-maroon-700 text-cream-50 text-xs font-bold tracking-wide hover:bg-maroon-600 transition-colors">APPLY</button>
            </div>

            {{-- Rating --}}
            <div class="bg-cream-50 border border-sand-400/40 rounded-lg p-4">
                <p class="text-xs font-bold text-maroon-900 uppercase tracking-wide mb-3">Rating</p>
                <div class="space-y-2">
                    @foreach ([4.5 => '4.5★ & up', 4.0 => '4.0★ & up', 0 => 'Any rating'] as $threshold => $label)
                        <button @click="minRating = {{ $threshold }}"
                                class="flex items-center gap-2 text-[13px] transition-colors duration-150"
                                :class="minRating === {{ $threshold }} ? 'text-maroon-700 font-semibold' : 'text-maroon-900/60 hover:text-maroon-700'">
                            <span class="w-3.5 h-3.5 rounded-full border-2 grid place-items-center shrink-0"
                                  :class="minRating === {{ $threshold }} ? 'border-maroon-700' : 'border-sand-400/70'">
                                <span x-show="minRating === {{ $threshold }}" class="w-1.5 h-1.5 rounded-full bg-maroon-700"></span>
                            </span>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <button @click="clearAll()" class="w-full h-10 rounded-md border border-sand-400/60 text-maroon-800 text-xs font-bold tracking-wide hover:bg-sand-400/15 transition-colors">
                CLEAR ALL
            </button>
        </aside>

        {{-- ============ MAIN ============ --}}
        <div class="md:col-span-4">

            {{-- Header --}}
            <div class="flex items-center gap-4 mb-6">
                <span class="w-14 h-14 rounded-lg bg-sand-400/25 grid place-items-center text-3xl shrink-0">{{ $category['icon'] }}</span>
                <div>
                    <h1 class="font-display text-3xl text-maroon-900">{{ $category['name'] }}</h1>
                    <p class="text-sm text-maroon-900/50 mt-0.5">{{ count($products) }} products</p>
                </div>
            </div>

            {{-- Sort bar --}}
            <div class="flex items-center gap-2 bg-cream-50 border border-sand-400/40 rounded-lg px-4 py-2.5 mb-5">
                <span class="text-xs text-maroon-900/50 font-medium mr-1">Sort by</span>
                @foreach (['popular' => 'Popular', 'latest' => 'Latest', 'topsales' => 'Top Sales'] as $mode => $label)
                    <button @click="setSort('{{ $mode }}')"
                            class="px-3.5 py-1.5 rounded-md text-xs font-semibold transition-colors duration-150"
                            :class="sortMode === '{{ $mode }}' ? 'bg-maroon-700 text-cream-50' : 'text-maroon-800 hover:bg-sand-400/20'">
                        {{ $label }}
                    </button>
                @endforeach
                <button @click="setSort('price')"
                        class="flex items-center gap-1 px-3.5 py-1.5 rounded-md text-xs font-semibold transition-colors duration-150"
                        :class="sortMode === 'price' ? 'bg-maroon-700 text-cream-50' : 'text-maroon-800 hover:bg-sand-400/20'">
                    Price
                    <span x-show="sortMode === 'price'" x-text="priceDir === 'asc' ? '↑' : '↓'"></span>
                </button>
            </div>

            {{-- Grid — flex-wrap (not CSS grid) so the `order` property below can actually reorder cards on sort --}}
            @if (count($products))
                <div class="flex flex-wrap gap-4">
                    @foreach ($products as $product)
                        @php
                            $slug = $product['slug'];
                            $rankExpr = "sortMode === 'latest' ? {$rankLatest[$slug]}"
                                      . " : sortMode === 'topsales' ? {$rankTopSales[$slug]}"
                                      . " : sortMode === 'price' ? (priceDir === 'asc' ? {$rankPriceAsc[$slug]} : {$rankPriceDesc[$slug]})"
                                      . " : {$rankPopular[$slug]}";
                        @endphp
                        <div class="w-[calc(50%-0.5rem)] md:w-[calc(33.333%-0.667rem)] lg:w-[calc(25%-0.75rem)]"
                             :style="'order: ' + ({{ $rankExpr }})"
                             x-show="(activeSub === 'all' || activeSub === '{{ $product['subcategory'] }}')
                                      && {{ (int) $product['price'] }} >= minPrice && {{ (int) $product['price'] }} <= maxPrice
                                      && {{ $product['rating'] }} >= minRating"
                             x-transition.opacity>
                            @include('partials.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-20 text-maroon-900/40">
                    <p class="text-4xl mb-3">🗂️</p>
                    <p class="text-sm">No products in this category yet.</p>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function categoryFilters() {
        return {
            activeSub: 'all',
            sortMode: 'popular',   // 'popular' | 'latest' | 'topsales' | 'price'
            priceDir: 'asc',
            minPrice: 0,
            maxPrice: 999999,
            minPriceInput: '',
            maxPriceInput: '',
            minRating: 0,

            setSort(mode) {
                if (mode === 'price' && this.sortMode === 'price') {
                    this.priceDir = this.priceDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortMode = mode;
                    if (mode === 'price') this.priceDir = 'asc';
                }
            },
            applyPriceRange() {
                this.minPrice = this.minPriceInput === '' ? 0 : Number(this.minPriceInput);
                this.maxPrice = this.maxPriceInput === '' ? 999999 : Number(this.maxPriceInput);
            },
            clearAll() {
                this.activeSub = 'all';
                this.sortMode = 'popular';
                this.priceDir = 'asc';
                this.minPrice = 0;
                this.maxPrice = 999999;
                this.minPriceInput = '';
                this.maxPriceInput = '';
                this.minRating = 0;
            }
        }
    }
</script>
@endpush