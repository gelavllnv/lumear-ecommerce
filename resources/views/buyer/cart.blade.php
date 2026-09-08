@extends('layouts.buyer')

@section('title', 'My Cart — Lumear')

@section('content')

@php
    $shops = [
        [
            'name' => 'Terra & Twine Home',
            'items' => [
                ['name' => 'Handwoven Rattan Pet Bed, Medium', 'variant' => 'Natural / Medium', 'price' => 899, 'qty' => 1, 'emoji' => '🐶', 'bg' => 'sand-400/25'],
                ['name' => 'Ceramic Pet Feeding Bowl Set', 'variant' => 'Charcoal', 'price' => 390, 'qty' => 2, 'emoji' => '🥣', 'bg' => 'gold-500/15'],
            ],
        ],
        [
            'name' => 'Northline Electronics',
            'items' => [
                ['name' => 'Wireless Noise-Cancel Earbuds', 'variant' => 'Matte Black', 'price' => 1299, 'qty' => 1, 'emoji' => '🎧', 'bg' => 'maroon-700/10'],
            ],
        ],
    ];
@endphp

<div x-data="cartPage()" class="max-w-6xl mx-auto px-4 md:px-6 pt-8 pb-24">
    <h1 class="font-display text-3xl text-maroon-900 mb-8">My Cart</h1>

    <div class="grid lg:grid-cols-3 gap-8">

        {{-- Items --}}
        <div class="lg:col-span-2 space-y-6">
            @foreach ($shops as $si => $shop)
                <div class="bg-cream-50 border border-sand-400/40 rounded-lg overflow-hidden">
                    <div class="flex items-center gap-3 px-5 py-4 border-b border-sand-400/30">
                        <input type="checkbox" x-model="shopChecked[{{ $si }}]" @change="toggleShop({{ $si }})"
                               class="w-4 h-4 rounded accent-maroon-700">
                        <span class="text-sm font-semibold text-maroon-900">🏪 {{ $shop['name'] }}</span>
                    </div>

                    @foreach ($shop['items'] as $ii => $item)
                        <div class="flex items-center gap-4 px-5 py-5 border-b border-sand-400/20 last:border-b-0">
                            <input type="checkbox" x-model="itemChecked['{{ $si }}-{{ $ii }}']" @change="recalc()" class="w-4 h-4 rounded accent-maroon-700">
                            <div class="w-16 h-16 rounded-xl bg-{{ $item['bg'] }} grid place-items-center text-2xl shrink-0">{{ $item['emoji'] }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-maroon-900 truncate">{{ $item['name'] }}</p>
                                <p class="text-xs text-maroon-900/45 mt-1">{{ $item['variant'] }}</p>
                            </div>
                            <div class="flex items-center border border-sand-400/60 rounded-md overflow-hidden shrink-0">
                                <button @click="changeQty('{{ $si }}-{{ $ii }}', -1)" class="w-8 h-8 grid place-items-center hover:bg-sand-400/25 transition-colors text-sm">−</button>
                                <span class="w-9 text-center text-sm" x-text="qty['{{ $si }}-{{ $ii }}'] ?? {{ $item['qty'] }}"></span>
                                <button @click="changeQty('{{ $si }}-{{ $ii }}', 1)" class="w-8 h-8 grid place-items-center hover:bg-sand-400/25 transition-colors text-sm">+</button>
                            </div>
                            <span class="w-20 text-right font-display text-maroon-700 font-semibold shrink-0">₱{{ number_format($item['price']) }}</span>
                            <button class="text-maroon-900/30 hover:text-maroon-600 transition-colors shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Summary --}}
        <div class="lg:col-span-1">
            <div class="sticky top-28 bg-cream-50 border border-sand-400/40 rounded-lg p-6 space-y-5">

                <div>
                    <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Voucher code</label>
                    <div class="flex gap-2 mt-2">
                        <input type="text" placeholder="e.g. LUMEAR50" class="flex-1 h-11 rounded-md bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                        <button class="px-5 rounded-md bg-maroon-700 text-cream-50 text-sm font-semibold hover:bg-maroon-600 transition-colors">Apply</button>
                    </div>
                </div>

                <div class="space-y-2.5 text-sm border-t border-sand-400/30 pt-5">
                    <div class="flex justify-between text-maroon-900/60"><span>Subtotal</span><span x-text="'₱' + subtotal.toLocaleString()"></span></div>
                    <div class="flex justify-between text-maroon-900/60"><span>Shipping fee</span><span>₱120</span></div>
                    <div class="flex justify-between text-maroon-600"><span>Voucher discount</span><span>−₱0</span></div>
                </div>

                <div class="flex justify-between items-baseline border-t border-sand-400/30 pt-4">
                    <span class="text-sm font-semibold text-maroon-900">Total</span>
                    <span class="font-display text-2xl text-maroon-700 font-semibold" x-text="'₱' + (subtotal + 120).toLocaleString()"></span>
                </div>

                <button class="w-full py-4 rounded-md bg-maroon-700 text-cream-50 font-bold text-sm tracking-wide hover:bg-maroon-600 hover:shadow-lift transition-all duration-200">
                    PLACE ORDER (<span x-text="selectedCount"></span>)
                </button>
                <p class="text-[11px] text-center text-maroon-900/40">Payment method and address confirmed at checkout</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function cartPage() {
        const prices = { '0-0': 899, '0-1': 390, '1-0': 1299 };
        const baseQty = { '0-0': 1, '0-1': 2, '1-0': 1 };
        return {
            itemChecked: { '0-0': true, '0-1': true, '1-0': true },
            shopChecked: { 0: true, 1: true },
            qty: { ...baseQty },
            subtotal: 0,
            selectedCount: 0,
            init() { this.recalc() },
            changeQty(key, delta) {
                this.qty[key] = Math.max(1, (this.qty[key] ?? baseQty[key]) + delta);
                this.recalc();
            },
            toggleShop(shopIndex) {
                Object.keys(prices).forEach(k => { if (k.startsWith(shopIndex + '-')) this.itemChecked[k] = this.shopChecked[shopIndex]; });
                this.recalc();
            },
            recalc() {
                let sum = 0, count = 0;
                Object.keys(prices).forEach(k => {
                    if (this.itemChecked[k]) { sum += prices[k] * (this.qty[k] ?? baseQty[k]); count++; }
                });
                this.subtotal = sum;
                this.selectedCount = count;
            }
        }
    }
</script>
@endpush