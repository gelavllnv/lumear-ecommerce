@extends('layouts.buyer')

@section('title', 'Messages — Lumear')

@section('content')

@php
    $threads = [
        ['shop' => 'Terra & Twine Home', 'last' => 'Your order has been packed and will ship tomorrow!', 'time' => '2m', 'unread' => 2, 'active' => true],
        ['shop' => 'Northline Electronics', 'last' => 'Thanks for your purchase 🎧', 'time' => '1h', 'unread' => 0, 'active' => false],
        ['shop' => 'Bloom & Basil', 'last' => 'You: Is this available in a bigger size?', 'time' => '1d', 'unread' => 0, 'active' => false],
        ['shop' => 'Cornerstone Books', 'last' => 'Glad you enjoyed the bundle!', 'time' => '3d', 'unread' => 0, 'active' => false],
    ];

    $messages = [
        ['from' => 'shop', 'text' => 'Hi! Thanks for ordering the Rattan Pet Bed 🐶', 'time' => '10:02 AM'],
        ['from' => 'me',   'text' => 'Hello, just wanted to check if it comes with a cushion cover?', 'time' => '10:05 AM'],
        ['from' => 'shop', 'text' => 'Yes it does — machine-washable too. We\'re packing it now.', 'time' => '10:06 AM'],
        ['from' => 'shop', 'text' => 'Your order has been packed and will ship tomorrow!', 'time' => '10:07 AM'],
    ];
@endphp

<div class="max-w-6xl mx-auto px-4 md:px-6 pt-8 pb-8 h-[calc(100vh-6rem)]">
    <div class="bg-cream-50 border border-sand-400/40 rounded-lg h-full flex overflow-hidden">

        {{-- Thread list --}}
        <aside class="w-full md:w-80 border-r border-sand-400/30 flex flex-col">
            <div class="px-5 py-4 border-b border-sand-400/30">
                <h1 class="font-display text-xl text-maroon-900">Messages</h1>
            </div>
            <div class="flex-1 overflow-y-auto">
                @foreach ($threads as $t)
                    <button class="w-full flex items-center gap-3 px-5 py-4 border-b border-sand-400/15 text-left transition-colors duration-200 {{ $t['active'] ? 'bg-sand-400/25' : 'hover:bg-sand-400/10' }}">
                        <span class="w-11 h-11 rounded-full bg-maroon-700/10 grid place-items-center text-lg shrink-0">🏪</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-maroon-900 truncate">{{ $t['shop'] }}</p>
                                <span class="text-[11px] text-maroon-900/40 shrink-0 ml-2">{{ $t['time'] }}</span>
                            </div>
                            <p class="text-xs text-maroon-900/50 truncate mt-0.5">{{ $t['last'] }}</p>
                        </div>
                        @if ($t['unread'])
                            <span class="w-5 h-5 rounded-full bg-maroon-700 text-cream-50 text-[10px] grid place-items-center shrink-0">{{ $t['unread'] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- Active thread --}}
        <section class="hidden md:flex flex-1 flex-col">
            <div class="flex items-center gap-3 px-6 py-4 border-b border-sand-400/30">
                <span class="w-10 h-10 rounded-full bg-maroon-700/10 grid place-items-center text-lg">🏪</span>
                <div>
                    <p class="text-sm font-semibold text-maroon-900">Terra & Twine Home</p>
                    <p class="text-[11px] text-maroon-600">Active now</p>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-4">
                @foreach ($messages as $m)
                    <div class="flex {{ $m['from'] === 'me' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-xs">
                            <div class="{{ $m['from'] === 'me' ? 'bg-maroon-700 text-cream-50' : 'bg-sand-400/25 text-maroon-900' }} rounded-lg px-4 py-2.5 text-sm leading-relaxed">
                                {{ $m['text'] }}
                            </div>
                            <p class="text-[10px] text-maroon-900/35 mt-1 {{ $m['from'] === 'me' ? 'text-right' : '' }}">{{ $m['time'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <form class="flex items-center gap-3 px-6 py-4 border-t border-sand-400/30">
                <input type="text" placeholder="Type a message…"
                       class="flex-1 h-12 rounded-md bg-sand-400/15 border border-sand-400/50 px-5 text-sm outline-none focus:border-maroon-500 transition-colors">
                <button class="w-12 h-12 rounded-md bg-maroon-700 text-cream-50 grid place-items-center hover:bg-maroon-600 transition-colors shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9-7-9-7v14zM3 12h9"/>
                    </svg>
                </button>
            </form>
        </section>
    </div>
</div>

@endsection