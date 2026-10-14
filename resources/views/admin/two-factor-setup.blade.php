@extends('layouts.app')

@section('title', 'Set up administrator 2FA — Lumear')

@section('content')
    <section class="mx-auto max-w-xl rounded-2xl bg-cream-50 p-8 shadow-soft"><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Administrator security</p><h1 class="mt-2 font-display text-4xl">Set up two-factor authentication</h1><p class="mt-4 text-sm leading-6 text-maroon-900/65">Add a new time-based account in Google Authenticator, Microsoft Authenticator, Authy, or another TOTP app. Then enter its six-digit code to activate access.</p><div class="mt-6 rounded-xl bg-cream-100 p-4"><p class="text-xs font-bold uppercase tracking-wider text-maroon-900/55">Setup key</p><code class="mt-2 block break-all text-lg font-bold text-maroon-900">{{ $credential->secret }}</code></div><form class="mt-6" method="post" action="{{ route('admin.2fa.confirm') }}">@csrf<label class="label">Authenticator code<input class="field mt-2" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label><button class="mt-4 rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50">Enable two-factor authentication</button></form></section>
@endsection
