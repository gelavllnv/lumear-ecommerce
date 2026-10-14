@extends('layouts.app')

@section('title', 'Your bag — Lumear')

@section('content')
    @php($total = $cart->items->sum(fn ($item) => $item->productVariant->price_minor * $item->quantity))

    <div class="mx-auto max-w-5xl rise">
        <div class="mb-7 flex items-end justify-between">
            <div><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Your selection</p><h1 class="mt-2 font-display text-4xl">Shopping bag</h1></div>
            <a class="text-sm font-bold underline" href="{{ route('shop') }}">Keep shopping</a>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <section class="space-y-3">
                @forelse ($cart->items as $item)
                    @php($variant = $item->productVariant)
                    @php($product = $variant->product)
                    <article class="hover-lift flex items-center gap-4 rounded-2xl bg-cream-50 p-4 shadow-soft sm:p-5">
                        <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-cream-100">
                            @if ($product->images->first())
                                <img class="h-full w-full object-cover" src="{{ route('media.show', ['path' => $product->images->first()->path]) }}" alt="{{ $product->name }}">
                            @else
                                ✦
                            @endif
                        </div>
                        <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $product->name }}</p><p class="mt-1 text-xs text-maroon-900/55">{{ $variant->name }} · {{ $product->seller->name }} · ₱{{ number_format($variant->price_minor / 100, 2) }} each</p></div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <form class="flex items-center gap-2" method="post" action="{{ route('cart.update', $item) }}">
                                @csrf
                                @method('PATCH')
                                <input aria-label="Quantity" class="w-14 rounded-lg border border-sand-400/60 bg-cream-50 p-2 text-center" name="quantity" type="number" min="0" max="{{ $variant->stock }}" value="{{ $item->quantity }}">
                                <button class="hidden text-xs font-bold underline sm:block">Update</button>
                            </form>
                            <form method="post" action="{{ route('cart.destroy', $item) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-maroon-700 underline hover:text-maroon-900">Remove</button></form>
                        </div>
                        <p class="hidden whitespace-nowrap font-display text-lg text-maroon-700 sm:block">₱{{ number_format(($variant->price_minor * $item->quantity) / 100, 2) }}</p>
                    </article>
                @empty
                    <div class="rounded-2xl bg-cream-50 p-12 text-center shadow-soft"><p class="font-display text-2xl">Your bag is still empty.</p><a class="mt-5 inline-block rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50" href="{{ route('shop') }}">Find something lovely</a></div>
                @endforelse
            </section>

            @if ($cart->items->isNotEmpty())
                <aside class="h-fit rounded-2xl bg-maroon-950 p-6 text-cream-50 shadow-lift">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-cream-200/70">Order summary</p>
                    <div class="mt-5 flex justify-between text-sm"><span>Subtotal</span><span>₱{{ number_format($total / 100, 2) }}</span></div>
                    <div class="flex justify-between border-b border-cream-50/15 py-4 text-sm"><span>Shipping</span><span>Set by seller after checkout</span></div>
                    <div class="mt-4 flex justify-between font-display text-xl"><span>Total</span><span>₱{{ number_format($total / 100, 2) }}</span></div>

                    @if (! $deliveryAddress)
                        <div class="mt-6 rounded-xl border border-amber-200/30 bg-amber-50/10 p-4 text-sm text-cream-100"><p class="font-bold">Delivery address needed</p><p class="mt-1 text-cream-200/75">Add an address before placing a Cash on Delivery order.</p><a class="mt-4 inline-block rounded-lg bg-cream-50 px-4 py-2 text-sm font-bold text-maroon-900" href="{{ route('buyer.address.create') }}">Add delivery address</a></div>
                    @else
                        <div class="mt-6 rounded-xl bg-cream-50/10 p-3 text-sm"><div class="flex items-start justify-between gap-3"><div><p class="font-bold">Deliver to {{ $deliveryAddress->recipient }}</p><p class="mt-1 text-cream-200/75">{{ $deliveryAddress->line1 }}, {{ $deliveryAddress->barangay }}, {{ $deliveryAddress->city }}, {{ $deliveryAddress->province }}</p></div><a class="shrink-0 text-xs font-bold underline" href="{{ route('buyer.address.create') }}">Change</a></div></div>
                        <form method="post" action="{{ route('checkout') }}" class="mt-4" data-checkout-form>
                            @csrf
                            <input type="hidden" name="payment_method" value="cod">
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <p class="rounded-xl bg-cream-50/10 p-3 text-sm"><strong>Cash on Delivery</strong><br><span class="text-cream-200/75">Pay the courier when your order arrives.</span></p>
                            <button class="glow mt-3 w-full rounded-xl bg-cream-50 p-3.5 text-sm font-bold text-maroon-900" data-checkout-button>Place COD order</button>
                        </form>
                    @endif
                </aside>
            @endif
        </div>
    </div>
@endsection
