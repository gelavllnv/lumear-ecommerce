@extends('layouts.app')

@section('title', 'Seller application — Lumear')

@section('content')
    <div class="mx-auto max-w-xl rounded-2xl bg-cream-50 p-8 shadow-soft">
        <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Seller Centre</p>
        <h1 class="mt-2 font-display text-4xl">{{ $seller->name }}</h1>
        <p class="mt-5 text-lg">Store status: <strong class="uppercase">{{ $seller->status }}</strong></p>

        @if(!auth()->user()->hasVerifiedEmail())
            <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">Verify your email before your approved store can access Seller Centre tools. <a class="font-bold underline" href="{{ route('seller.email-verification.notice') }}">Enter verification code</a></div>
        @endif

        @if($seller->status === 'approved' && auth()->user()->hasVerifiedEmail())
            <a class="mt-6 inline-block rounded-xl bg-maroon-950 px-5 py-3 text-sm font-bold text-cream-50" href="{{ route('seller.dashboard') }}">Open Seller Centre</a>
        @elseif($seller->status === 'pending')
            <p class="mt-4 text-sm text-maroon-900/65">Submit your verification and payout details, then an administrator will review your store.</p>
            <a class="mt-6 inline-block rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50" href="{{ route('seller.onboarding') }}">Continue onboarding</a>
        @elseif($seller->status === 'rejected')
            <p class="mt-4 text-sm text-red-800">Reason: {{ $seller->rejection_reason }}</p>
            <a class="mt-6 inline-block text-sm font-bold text-maroon-700 underline" href="{{ route('seller.onboarding') }}">Update and resubmit documents</a>
        @else
            <p class="mt-4 text-sm text-maroon-900/65">Your seller account is suspended. Please contact support.</p>
        @endif
    </div>
@endsection
