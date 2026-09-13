@extends('layouts.buyer')

@section('title', ($query ?? '') !== '' ? '"'.$query.'" — Search results — Lumear' : 'Search — Lumear')

@section('content')

@php
    $query = $query ?? '';
    $products = $products ?? [];

    // Same rank-precomputation approach as the category page — see that file for the
    // full reasoning on why sort uses CSS `order` instead of re-rendering.
    $indexed       = collect($products)->values();
    $rankPopular   = $indexed->pluck('slug')->flip();
    $rankLatest    = $indexed->reverse()->values()->pluck('slug')->flip();
    $rankTopSales  = $indexed->sortByDesc(fn ($p) => (int) $p['sold'])->values()->pluck('slug')->flip();
    $rankPriceAsc  = $indexed->sortBy(fn ($p) => (int) $p['price'])->values()->pluck('slug')->flip();
    $rankPriceDesc = $indexed->sortByDesc(fn ($p) => (int) $p['price'])->values()->pluck('slug')->flip();
@endphp

<div x-data="searchSort()" class="max-w-7xl mx-auto px-4 md:px-6 pt-8 pb-20">

    <p class="text-xs text-maroon-900/50 mb-5">
        <a href="{{ url('/home') }}" class="hover:text-maroon-700">Home</a> ·
        <span class="text-maroon-900">Search results</span>
    </p>

    <div class="flex items-center gap-4 mb-6">
        <span class="w-14 h-14 rounded-lg bg-sand-400/25 grid place-items-center text-3xl shrink-0">🔍</span>
        <div>
            <h1 class="font-display text-3xl text-maroon-900">
                @if ($query !== '')
                    Results for "{{ $query }}"
                @else
                    Search
                @endif
            </h1>
            <p class="text-sm text-maroon-900/50 mt-0.5">{{ count($products) }} {{ Str::plural('product', count($products)) }} found</p>
        </div>
    </div>

    @if ($query === '')

        <div class="text-center py-20 text-maroon-900/40">
            <p class="text-4xl mb-3">🔎</p>
            <p class="text-sm">Type something into the search bar up top to look through the catalog.</p>
        </div>

    @elseif (count($products))

        {{-- Sort bar — identical pattern to the category page --}}
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

        <div class="flex flex-wrap gap-4">
            @foreach ($products as $product)
                @php
                    $slug = $product['slug'];
                    $rankExpr = "sortMode === 'latest' ? {$rankLatest[$slug]}"
                              . " : sortMode === 'topsales' ? {$rankTopSales[$slug]}"
                              . " : sortMode === 'price' ? (priceDir === 'asc' ? {$rankPriceAsc[$slug]} : {$rankPriceDesc[$slug]})"
                              . " : {$rankPopular[$slug]}";
                @endphp
                <div class="w-[calc(50%-0.5rem)] md:w-[calc(25%-0.75rem)] lg:w-[calc(20%-0.8rem)]" :style="'order: ' + ({{ $rankExpr }})">
                    @include('partials.product-card', ['product' => $product])
                </div>
            @endforeach
        </div>

    @else

        <div class="text-center py-20 text-maroon-900/40">
            <p class="text-4xl mb-3">🗂️</p>
            <p class="text-sm">Nothing matched "{{ $query }}" — try a different word, or browse by category instead.</p>
        </div>

    @endif
</div>

@endsection

@push('scripts')
<script>
    function searchSort() {
        return {
            sortMode: 'popular',
            priceDir: 'asc',
            setSort(mode) {
                if (mode === 'price' && this.sortMode === 'price') {
                    this.priceDir = this.priceDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortMode = mode;
                    if (mode === 'price') this.priceDir = 'asc';
                }
            }
        }
    }
</script>
@endpush