@extends('layouts.app')

@section('title', $product->name.' — Lumear')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buyer/flash-deals.css') }}?v=1">
@endpush

@section('content')
    @php($promotion = $product->buyer_promotion)

    <div class="rise">
        <a class="text-xs font-bold uppercase tracking-wider text-maroon-700 hover:underline" href="{{ route('shop') }}">← Continue browsing</a>

        <div class="mt-5 grid gap-9 lg:grid-cols-2">
            <div class="grid min-h-[420px] place-items-center overflow-hidden rounded-3xl bg-cream-50 shadow-soft">
                @if ($product->images->first())
                    <img class="h-full w-full max-h-[560px] object-contain" src="{{ route('media.show', ['path' => $product->images->first()->path]) }}" alt="{{ $product->name }}">
                @else
                    <span class="text-7xl">✦</span>
                @endif
            </div>

            <div class="py-2">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">{{ $product->category?->name ?? 'Lumear find' }}</p>
                <h1 class="mt-3 font-display text-4xl leading-tight">{{ $product->name }}</h1>

                <div class="mt-3 flex items-center gap-2 text-sm">
                    <span class="tracking-wider text-amber-600">{{ $product->reviews_count ? str_repeat('★', (int) round($product->reviews_avg_rating)).str_repeat('☆', 5 - (int) round($product->reviews_avg_rating)) : '☆☆☆☆☆' }}</span>
                    <span class="text-maroon-900/60">{{ $product->reviews_count ? number_format($product->reviews_avg_rating, 1).' · '.$product->reviews_count.' verified rating'.($product->reviews_count === 1 ? '' : 's') : 'No ratings yet' }}</span>
                </div>

                <div class="mt-5">
                    @if ($promotion['label'])
                        <span class="inline-flex rounded-full bg-maroon-700 px-3 py-1 text-xs font-bold text-cream-50">{{ $promotion['label'] }}</span>
                    @endif
                    <p class="font-display text-3xl text-maroon-700">From ₱{{ number_format($promotion['price_minor'] / 100, 2) }}</p>
                    @if ($promotion['label'])
                        <p class="mt-1 text-sm text-maroon-900/50"><del>Regularly from ₱{{ number_format($promotion['regular_price_minor'] / 100, 2) }}</del> · Final deal price is verified in your bag.</p>
                    @endif
                    @if ($promotion['flash_ends_at'])
                        <p class="product-flash-detail" data-flash-countdown="{{ $promotion['flash_ends_at'] }}">Ends soon</p>
                    @endif
                </div>

                <div class="mt-6 border-y border-sand-400/35 py-5">
                    <p class="leading-relaxed text-maroon-900/75">{{ $product->description }}</p>
                    <p class="mt-4 text-sm"><b>{{ $product->stock }}</b> pieces available across options</p>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-cream-200 font-display">{{ strtoupper(substr($product->seller->name, 0, 1)) }}</span>
                    <p class="text-sm">Sold by<br><a class="font-bold underline" href="{{ route('shops.show', $product->seller) }}">{{ $product->seller->name }}</a></p>
                </div>

                @if ($availableVouchers->isNotEmpty())
                    <section class="mt-6 rounded-2xl border border-sand-400/45 bg-cream-100 p-4" aria-labelledby="product-vouchers-title">
                        <h2 id="product-vouchers-title" class="text-sm font-bold">Store vouchers</h2>
                        <div class="mt-3 space-y-2">
                            @foreach ($availableVouchers as $voucher)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-cream-50 p-3 text-sm">
                                    <p><strong>{{ $voucher->discount_type === 'fixed' ? '₱'.number_format($voucher->discount_minor / 100, 2).' off' : $voucher->discount_percent.'% off' }}</strong><span class="block text-xs text-maroon-900/60">Min. spend ₱{{ number_format($voucher->minimum_spend_minor / 100, 2) }} · Code {{ $voucher->code }}</span></p>
                                    @auth
                                        @if (auth()->user()->isBuyer())
                                            @if ($claimedVoucherIds->contains($voucher->id))
                                                <span class="text-xs font-bold text-emerald-700">Saved</span>
                                            @else
                                                <form method="post" action="{{ route('buyer.vouchers.claim', $voucher) }}">@csrf<button class="rounded-lg bg-maroon-700 px-3 py-2 text-xs font-bold text-cream-50">Claim</button></form>
                                            @endif
                                        @endif
                                    @else
                                        <a class="text-xs font-bold underline" href="{{ route('login') }}">Sign in to claim</a>
                                    @endauth
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($availableBundles->isNotEmpty())
                    <section class="mt-4 rounded-2xl border border-sand-400/45 bg-cream-100 p-4" aria-labelledby="product-bundles-title">
                        <h2 id="product-bundles-title" class="text-sm font-bold">Bundle offers</h2>
                        <div class="mt-3 space-y-2">
                            @foreach ($availableBundles as $bundle)
                                <div class="rounded-xl bg-cream-50 p-3 text-sm">
                                    <p><strong>{{ $bundle->name }}</strong><span class="block mt-1 text-xs text-maroon-900/60">{{ $bundle->type === 'percentage_bundle' ? 'Add '.$bundle->buy_quantity.' qualifying items for '.$bundle->discount_percent.'% off.' : 'Buy '.$bundle->buy_quantity.' qualifying items and add a reward to get '.$bundle->get_quantity.' free.' }}</span></p>
                                    <p class="mt-2 text-xs text-maroon-900/60">Qualifying products: {{ $bundle->qualifierProducts->pluck('name')->join(', ') }}@if($bundle->type === 'buy_x_get_y') · Rewards: {{ $bundle->rewardProducts->pluck('name')->join(', ') }}@endif</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="mt-7">
                    @auth
                        @if (auth()->user()->isBuyer())
                            <form class="space-y-4" method="post" action="{{ route('cart.add', $product) }}">
                                @csrf
                                <label class="block text-sm font-bold" for="variant_id">Choose an option
                                    <select id="variant_id" class="mt-2 block w-full rounded-xl border border-sand-400/60 bg-cream-50 p-3" name="variant_id">
                                        @foreach ($product->variants as $variant)
                                            <option value="{{ $variant->id }}">{{ $variant->name }} — ₱{{ number_format($variant->price_minor / 100, 2) }} · {{ $variant->stock }} left</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="flex flex-wrap gap-3">
                                    <div class="flex items-center rounded-xl border border-sand-400/60 bg-cream-50"><label class="sr-only" for="quantity">Quantity</label><input id="quantity" class="w-16 bg-transparent p-3 text-center outline-none" type="number" name="quantity" value="1" min="1"></div>
                                    <button class="glow rounded-xl bg-maroon-700 px-7 py-3 text-sm font-bold text-cream-50 transition hover:bg-maroon-900">Add to bag</button>
                                    <a class="rounded-xl border border-maroon-700 px-5 py-3 text-sm font-bold text-maroon-700 transition hover:bg-cream-200" href="{{ route('cart') }}">View bag</a>
                                </div>
                            </form>
                            <form class="mt-3 flex gap-2" method="post" action="{{ route('buyer.messages.start', $product->seller) }}">
                                @csrf
                                <input name="product_id" type="hidden" value="{{ $product->id }}">
                                <label class="sr-only" for="seller-message">Message the seller about {{ $product->name }}</label>
                                <input id="seller-message" class="field flex-1" name="body" maxlength="1000" placeholder="Ask about {{ $product->name }}" required>
                                <button class="rounded-xl border border-maroon-700 px-4 py-2 text-sm font-bold text-maroon-700">Message seller</button>
                            </form>
                        @elseif(auth()->user()->isSeller())
                            <div class="rounded-xl border border-sand-400/50 bg-cream-100 p-4 text-sm text-maroon-900/70"><p class="font-bold text-maroon-900">Seller account</p><p class="mt-1">Seller accounts manage products and fulfillment, and cannot add products to a shopping bag.</p><a class="mt-3 inline-block font-bold underline" href="{{ route('seller.status') }}">Open Seller Centre</a></div>
                        @else
                            <div class="rounded-xl border border-sand-400/50 bg-cream-100 p-4 text-sm text-maroon-900/70">This account cannot make purchases.</div>
                        @endif
                    @else
                        <div class="flex flex-wrap gap-3"><a class="glow rounded-xl bg-maroon-700 px-7 py-3 text-sm font-bold text-cream-50" href="{{ route('login') }}">Sign in to buy</a><a class="rounded-xl border border-maroon-700 px-5 py-3 text-sm font-bold text-maroon-700" href="{{ route('register') }}">Create buyer account</a></div>
                    @endauth
                </div>

                <p class="mt-4 text-xs text-maroon-900/55">Stock, promotional pricing, and vouchers are verified again at checkout.</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/buyer/flash-deals.js') }}?v=2" defer></script>
@endpush
