@extends('layouts.app')

@section('title', 'Administrator verification — Lumear')

@section('content')
    <section class="mx-auto max-w-md rounded-2xl bg-cream-50 p-8 shadow-soft"><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Administrator security</p><h1 class="mt-2 font-display text-4xl">Verify your identity</h1><p class="mt-4 text-sm text-maroon-900/65">Enter the current six-digit code from your authenticator app to continue to the Admin Centre.</p><form class="mt-6" method="post" action="{{ route('admin.2fa.verify') }}">@csrf<label class="label">Authenticator code<input class="field mt-2" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label><button class="mt-4 w-full rounded-xl bg-maroon-700 py-3 text-sm font-bold text-cream-50">Verify and continue</button></form></section>
@endsection
