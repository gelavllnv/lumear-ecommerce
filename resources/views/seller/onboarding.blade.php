@extends('layouts.app')

@section('title', 'Seller onboarding — Lumear')

@section('content')
    @php($completed = collect($steps)->where('complete', true)->count())
    <div class="mx-auto max-w-5xl">
        <div class="mb-7 rounded-2xl bg-maroon-950 p-7 text-cream-50 shadow-lift">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-cream-200/70">Seller verification</p>
            <h1 class="mt-2 font-display text-4xl">Prepare {{ $seller->name }} for review</h1>
            <progress class="mt-6 h-2 w-full overflow-hidden rounded-full" value="{{ $completed }}" max="{{ max(count($steps), 1) }}">{{ $completed }}/{{ count($steps) }}</progress>
            <p class="mt-2 text-sm text-cream-200/75">{{ $completed }} of {{ count($steps) }} onboarding steps complete.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[.85fr_1.15fr]">
            <aside class="rounded-2xl bg-cream-50 p-6 shadow-soft">
                <h2 class="font-display text-2xl">Checklist</h2>
                <ol class="mt-5 space-y-4">@foreach($steps as $step)<li class="flex items-center gap-3 text-sm"><span class="grid h-6 w-6 place-items-center rounded-full {{ $step['complete'] ? 'bg-emerald-700 text-white' : 'bg-cream-200 text-maroon-900/50' }}">{{ $step['complete'] ? '✓' : '•' }}</span><span>{{ $step['label'] }}</span></li>@endforeach</ol>
                <a class="mt-7 inline-block text-sm font-bold text-maroon-700 underline" href="{{ route('seller.status') }}">View application status</a>
            </aside>

            <div class="space-y-6">
                <section class="rounded-2xl bg-cream-50 p-6 shadow-soft">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Store profile</p>
                    <h2 class="mt-2 font-display text-2xl">Store and pickup address</h2>
                    <form class="mt-5 grid gap-4 sm:grid-cols-2" method="post" action="{{ route('seller.onboarding.store-profile') }}">@csrf
                        <label class="mini-label sm:col-span-2">Store name<input class="field" name="business_name" value="{{ old('business_name', $seller->name) }}" required></label>
                        <label class="mini-label sm:col-span-2">Store category<select class="field" name="category_id" @disabled($seller->category_id) required>@if(! $seller->category_id)<option value="">Choose the one category your shop will sell</option>@endif @foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $seller->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select><p class="mt-2 text-xs text-maroon-900/55">A shop may sell products from one category only. This choice is locked after saving.</p></label>
                        <label class="mini-label">Pickup contact<input class="field" name="pickup_recipient" value="{{ old('pickup_recipient', $seller->pickupAddress?->recipient) }}" required></label>
                        <label class="mini-label">Pickup phone<input class="field" name="pickup_phone" value="{{ old('pickup_phone', $seller->pickupAddress?->phone) }}" required></label>
                        @include('partials.address-fields', ['prefix' => 'pickup', 'address' => $seller->pickupAddress])
                        <div class="sm:col-span-2"><button class="rounded-xl border border-maroon-700 px-5 py-3 text-sm font-bold text-maroon-700">Save store profile</button></div>
                    </form>
                </section>

                <section class="rounded-2xl bg-cream-50 p-6 shadow-soft">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Identity verification</p>
                    <h2 class="mt-2 font-display text-2xl">KYC documents</h2>
                    @if($kyc)<p class="mt-3 rounded-lg bg-cream-100 p-3 text-sm">Latest submission: <strong class="capitalize">{{ $kyc->status }}</strong>@if($kyc->rejection_reason) · {{ $kyc->rejection_reason }}@endif</p>@endif
                    <form class="mt-5 grid gap-4 sm:grid-cols-2" method="post" action="{{ route('seller.onboarding.kyc') }}" enctype="multipart/form-data">@csrf
                        <label class="mini-label sm:col-span-2">Tax number<input class="field" name="tax_number" value="{{ old('tax_number', $kyc?->tax_number) }}" required></label>
                        <label class="mini-label">Government ID<input class="field" name="government_id" type="file" accept="image/*,.pdf" required></label>
                        @if(($seller->profile?->business_type ?? null) !== 'individual')<label class="mini-label">Business registration document<input class="field" name="business_permit" type="file" accept="image/*,.pdf" required></label>@endif
                        <label class="mini-label sm:col-span-2">Selfie check (optional)<input class="field" name="selfie" type="file" accept="image/*"></label>
                        <div class="sm:col-span-2"><button class="rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50">Submit KYC securely</button></div>
                    </form>
                </section>

                <section class="rounded-2xl bg-cream-50 p-6 shadow-soft">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Manual payouts only</p>
                    <h2 class="mt-2 font-display text-2xl">Where should we send earnings?</h2>
                    <p class="mt-2 text-sm text-maroon-900/60">Your details are encrypted and used only by an administrator when processing an approved withdrawal outside Lumear.</p>
                    <form class="mt-5 grid gap-4 sm:grid-cols-2" method="post" action="{{ route('seller.onboarding.payout-account') }}">@csrf
                        <label class="mini-label">Payout method<select class="field" name="type"><option value="bank">Bank account</option><option value="e_wallet">E-wallet</option></select></label>
                        <label class="mini-label">Account holder<input class="field" name="account_name" value="{{ old('account_name', $payoutAccount?->account_name) }}" required></label>
                        <label class="mini-label sm:col-span-2">Account or wallet number<input class="field" name="account_number" inputmode="numeric" value="{{ old('account_number') }}" placeholder="Enter a new value to replace the saved account" required></label>
                        <div class="sm:col-span-2"><button class="rounded-xl border border-maroon-700 px-5 py-3 text-sm font-bold text-maroon-700">Save payout details</button></div>
                    </form>
                </section>

                <section class="rounded-2xl bg-cream-50 p-6 shadow-soft">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Seller agreement v1.0</p>
                    <p class="mt-2 text-sm leading-6 text-maroon-900/65">I understand that Lumear currently accepts only COD from buyers, and that payouts, refunds, and transfer records are handled manually by the marketplace team.</p>
                    <form class="mt-4" method="post" action="{{ route('seller.onboarding.agreement') }}">@csrf<label class="flex gap-2 text-sm"><input name="agreement" type="checkbox" value="1" required>I accept the Seller Agreement.</label><button class="mt-4 rounded-xl border border-maroon-700 px-5 py-3 text-sm font-bold text-maroon-700">Accept agreement</button></form>
                </section>
            </div>
        </div>
    </div>
@endsection
