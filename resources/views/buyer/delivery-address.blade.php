@extends('layouts.app')

@section('title', 'Delivery address — Lumear')

@section('content')
    <section class="mx-auto max-w-2xl rise">
        <a class="text-xs font-bold uppercase tracking-wider text-maroon-700 hover:underline" href="{{ route('cart') }}">← Back to bag</a>
        <div class="mt-5 rounded-2xl bg-cream-50 p-6 shadow-soft sm:p-8">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Checkout requirement</p>
            <h1 class="mt-2 font-display text-4xl">Add a delivery address</h1>
            <p class="mt-3 text-sm leading-6 text-maroon-900/65">We need a complete Philippine delivery address before a Cash on Delivery order can be placed.</p>

            <form class="mt-7 space-y-4" method="post" action="{{ route('buyer.address.store') }}">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="label">Recipient name<input class="field mt-2" name="recipient" value="{{ old('recipient', $address?->recipient) }}" required></label>
                    <label class="label">Mobile number<input class="field mt-2" name="phone" value="{{ old('phone', $address?->phone) }}" required></label>
                </div>
                @include('partials.address-fields', ['address' => $address])
                <button class="glow w-full rounded-xl bg-maroon-700 p-3.5 text-sm font-bold text-cream-50">Save delivery address</button>
            </form>
        </div>
    </section>
@endsection
