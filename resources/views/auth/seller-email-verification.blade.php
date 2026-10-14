@extends('layouts.auth')

@section('title', 'Verify your seller email')

@section('content')
    <div class="auth-card mx-auto max-w-md rounded-[2rem] bg-cream-50 p-8 text-center rise sm:p-10">
        <p class="auth-pill inline-flex rounded-full px-3 py-2 text-[11px] font-bold tracking-[.14em] text-maroon-700">ONE MORE STEP</p>
        <h1 class="mt-5 font-display text-4xl text-maroon-900">Verify your email</h1>
        <p class="mt-3 text-sm leading-6 text-maroon-900/65">Enter the six-digit code sent to your email. It expires after 10 minutes.</p>
        <form method="post" action="{{ route('seller.email-verification.verify') }}" class="mt-7 space-y-4">@csrf
            <input class="field text-center text-xl tracking-[.45em]" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" placeholder="000000" required autofocus>
            <button class="glow w-full rounded-xl bg-maroon-700 p-3.5 font-bold text-cream-50">Verify email</button>
        </form>
        <form method="post" action="{{ route('seller.email-verification.resend') }}" class="mt-4">@csrf <button class="text-sm font-semibold text-maroon-700 underline">Send a new code</button></form>
    </div>
@endsection
