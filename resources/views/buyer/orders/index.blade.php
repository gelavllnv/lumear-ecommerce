@extends('layouts.buyer')

@section('title', 'My Purchases — Lumear')

@section('content')

@php
    $tabs = [
        'to_pay'     => 'To Pay',
        'to_ship'    => 'To Ship',
        'in_transit' => 'In Transit',
        'to_receive' => 'Out for Delivery',
        'completed'  => 'Completed',
        'cancelled'  => 'Cancelled',
    ];

    $orders = [
        ['status' => 'to_ship',    'shop' => 'Terra & Twine Home',     'item' => 'Handwoven Rattan Pet Bed, Medium', 'variant' => 'Natural / Medium', 'qty' => 1, 'price' => 899, 'emoji' => '🐶', 'bg' => 'sand-400/25', 'note' => 'Seller is preparing your order'],
        ['status' => 'in_transit', 'shop' => 'Northline Electronics',  'item' => 'Wireless Noise-Cancel Earbuds',    'variant' => 'Matte Black',       'qty' => 1, 'price' => 1299,'emoji' => '🎧', 'bg' => 'maroon-700/10', 'note' => 'Picked up by courier · Sep 3'],
        ['status' => 'to_receive', 'shop' => 'Bloom & Basil',          'item' => 'Terracotta Planter Trio',           'variant' => 'Set of 3',          'qty' => 1, 'price' => 480, 'emoji' => '🪴', 'bg' => 'gold-500/15', 'note' => 'Out for delivery — arriving today'],
        ['status' => 'completed',  'shop' => 'Cornerstone Books',      'item' => 'Paperback Bestseller Bundle x3',    'variant' => 'Fiction Mix',       'qty' => 1, 'price' => 890, 'emoji' => '📖', 'bg' => 'sand-400/25', 'note' => 'Delivered Aug 27'],
        ['status' => 'completed',  'shop' => 'Fielder & Co.',          'item' => 'Adjustable Dumbbell Set 20kg',      'variant' => 'Black',             'qty' => 1, 'price' => 3100,'emoji' => '🏋️', 'bg' => 'maroon-700/10', 'note' => 'Delivered Aug 19'],
        ['status' => 'cancelled',  'shop' => 'Petal & Stem Jewelry',   'item' => "14K Gold-Plated Hoop Earrings",     'variant' => 'Small',             'qty' => 1, 'price' => 520, 'emoji' => '💫', 'bg' => 'gold-500/15', 'note' => 'Cancelled by buyer'],
    ];
@endphp

<div x-data="{ active: 'to_ship' }" class="max-w-5xl mx-auto px-4 md:px-6 pt-8 pb-20">
    <h1 class="font-display text-3xl text-maroon-900 mb-6">My Purchases</h1>

    {{-- Tabs --}}
    <div class="flex gap-1 overflow-x-auto border-b border-sand-400/50">
        @foreach ($tabs as $key => $label)
            <button @click="active = '{{ $key }}'"
                    class="shrink-0 px-5 py-3.5 text-sm font-semibold border-b-2 transition-colors duration-200"
                    :class="active === '{{ $key }}' ? 'border-maroon-700 text-maroon-900' : 'border-transparent text-maroon-900/40 hover:text-maroon-900/70'">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Order list --}}
    <div class="mt-6 space-y-4">
        @foreach ($tabs as $key => $label)
            <div x-show="active === '{{ $key }}'" x-cloak x-transition.opacity class="space-y-4">
                @php $filtered = collect($orders)->where('status', $key); @endphp

                @forelse ($filtered as $order)
                    <div class="bg-cream-50 border border-sand-400/40 rounded-lg overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-sand-400/30">
                            <span class="text-sm font-semibold text-maroon-900">🏪 {{ $order['shop'] }}</span>
                            <span class="text-xs font-medium text-maroon-600 bg-maroon-700/10 px-3 py-1 rounded-md">{{ $label }}</span>
                        </div>
                        <div class="flex items-center gap-4 px-5 py-5">
                            <div class="w-16 h-16 rounded-xl bg-{{ $order['bg'] }} grid place-items-center text-2xl shrink-0">{{ $order['emoji'] }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-maroon-900">{{ $order['item'] }}</p>
                                <p class="text-xs text-maroon-900/45 mt-1">{{ $order['variant'] }} · Qty {{ $order['qty'] }}</p>
                                <p class="text-xs text-maroon-600 mt-1.5">{{ $order['note'] }}</p>
                            </div>
                            <span class="font-display text-lg text-maroon-700 font-semibold shrink-0">₱{{ number_format($order['price']) }}</span>
                        </div>
                        <div class="flex items-center justify-end gap-3 px-5 py-3.5 border-t border-sand-400/30">
                            @if ($key === 'completed')
                                <button class="px-5 py-2 rounded-md border border-sand-400/60 text-sm text-maroon-800 hover:bg-sand-400/20 transition-colors">Buy Again</button>
                                <button class="px-5 py-2 rounded-md bg-maroon-700 text-cream-50 text-sm font-semibold hover:bg-maroon-600 transition-colors">Rate & Review</button>
                            @elseif ($key === 'to_ship' || $key === 'in_transit' || $key === 'to_receive')
                                <button class="px-5 py-2 rounded-md border border-sand-400/60 text-sm text-maroon-800 hover:bg-sand-400/20 transition-colors">Track Order</button>
                                <button class="px-5 py-2 rounded-md bg-maroon-700 text-cream-50 text-sm font-semibold hover:bg-maroon-600 transition-colors">Contact Seller</button>
                            @elseif ($key === 'to_pay')
                                <button class="px-5 py-2 rounded-md bg-maroon-700 text-cream-50 text-sm font-semibold hover:bg-maroon-600 transition-colors">Pay Now</button>
                            @else
                                <button class="px-5 py-2 rounded-md border border-sand-400/60 text-sm text-maroon-800 hover:bg-sand-400/20 transition-colors">Buy Again</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16 text-maroon-900/40">
                        <p class="text-4xl mb-3">🗂️</p>
                        <p class="text-sm">No orders in "{{ $label }}" right now.</p>
                    </div>
                @endforelse
            </div>
        @endforeach
    </div>
</div>

@endsection