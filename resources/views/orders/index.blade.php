@extends('layouts.app')
@section('title','My orders — Lumear')

@section('content')
<div class="rise mx-auto max-w-5xl">
    <div class="mb-7 flex items-end justify-between">
        <div><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Order history</p><h1 class="mt-2 font-display text-4xl">My orders</h1></div>
        <a class="text-sm font-bold underline" href="{{ route('shop') }}">Continue shopping</a>
    </div>
    <div class="space-y-4">
        @forelse ($orders as $order)
            <article class="overflow-hidden rounded-2xl bg-cream-50 shadow-soft">
                <div class="flex items-center justify-between gap-4 border-b border-sand-400/25 px-5 py-4">
                    <div><p class="font-bold">{{ $order->reference }}</p><p class="mt-1 text-xs text-maroon-900/50">Placed {{ $order->created_at->toFormattedDateString() }} · {{ strtoupper($order->payment_method) }}</p></div>
                    <span class="rounded-full bg-cream-200 px-3 py-1.5 text-xs font-bold capitalize">{{ $order->payment_status }}</span>
                </div>
                <div class="divide-y divide-sand-400/20">
                    @foreach ($order->sellerOrders as $sellerOrder)
                        <section class="px-5 py-4">
                            <div class="mb-3 flex items-center justify-between gap-3 text-sm"><p class="font-semibold">{{ $sellerOrder->seller->name }}</p><span class="rounded-full bg-maroon-900/5 px-3 py-1 text-xs font-bold capitalize">{{ str_replace('_', ' ', $sellerOrder->status) }}</span></div>
                            @if ($sellerOrder->status === 'delivered')
                                <div class="mb-4 rounded-xl border border-emerald-700/20 bg-emerald-50 p-4"><p class="font-bold text-emerald-950">Have you received this parcel?</p><p class="mt-1 text-sm text-emerald-900/80">Confirming receipt completes this COD order and lets you leave product ratings. It does not charge you again or send money online.</p><form class="mt-3" method="post" action="{{ route('buyer.orders.confirm-receipt', $sellerOrder) }}">@csrf<button class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-950">Confirm receipt &amp; release COD confirmation</button></form></div>
                            @elseif ($sellerOrder->status === 'completed')
                                <p class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">✓ Receipt confirmed — you can rate your purchased products below.</p>
                            @endif
                            <div class="space-y-3">
                                @foreach ($sellerOrder->items as $item)
                                    <div class="rounded-xl bg-cream-100/65 p-3">
                                        <div class="flex flex-wrap items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-xl bg-cream-100">✦</span><div class="min-w-40 flex-1"><p class="font-semibold">{{ $item->product_name }}</p><p class="mt-1 text-xs text-maroon-900/55">{{ $item->variant_name }} · Qty {{ $item->quantity }}</p></div><p class="font-display text-lg text-maroon-700">₱{{ number_format($item->lineTotalMinor() / 100, 2) }}</p></div>
                                        @if ($sellerOrder->status === 'completed')
                                            <form class="mt-4 border-t border-sand-400/20 pt-4" method="post" action="{{ route('buyer.reviews.store', $item) }}">
                                                @csrf
                                                <div class="flex flex-wrap items-center gap-2"><label class="text-sm font-bold" for="rating-{{ $item->id }}">Your rating</label><select class="rounded-lg border border-sand-400/60 bg-cream-50 px-3 py-2 text-sm" id="rating-{{ $item->id }}" name="rating" required>@for ($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}" @selected(($item->review?->rating ?? 5) === $rating)>{{ str_repeat('★', $rating) }}{{ str_repeat('☆', 5 - $rating) }} ({{ $rating }}/5)</option>@endfor</select></div>
                                                <label class="sr-only" for="review-{{ $item->id }}">Review message</label><textarea class="mt-3 w-full rounded-lg border border-sand-400/60 bg-cream-50 p-3 text-sm" id="review-{{ $item->id }}" name="body" rows="2" maxlength="2000" placeholder="Share your experience (optional)">{{ $item->review?->body }}</textarea><button class="mt-2 rounded-xl bg-maroon-700 px-4 py-2 text-sm font-bold text-cream-50 transition hover:bg-maroon-900">{{ $item->review ? 'Update rating' : 'Submit rating' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                <div class="flex justify-between bg-cream-100/65 px-5 py-4"><span class="text-sm text-maroon-900/60">Order total</span><span class="font-display text-xl text-maroon-700">₱{{ number_format($order->total_minor / 100, 2) }}</span></div>
            </article>
        @empty
            <div class="rounded-2xl bg-cream-50 p-12 text-center shadow-soft"><p class="font-display text-2xl">Nothing ordered yet.</p><a class="mt-5 inline-block rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50" href="{{ route('shop') }}">Start exploring</a></div>
        @endforelse
    </div>
</div>
@endsection
