@extends('layouts.app')

@section('title', 'Verify your email — Lumear')

@section('content')
    <section class="mx-auto max-w-xl rounded-2xl bg-cream-50 p-8 text-center shadow-soft">
        <p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">One more step</p>
        <h1 class="mt-3 font-display text-4xl">Verify your email</h1>
        @if (config('mail.default') === 'log')
            <p class="mx-auto mt-4 max-w-md text-sm leading-6 text-maroon-900/65">Email delivery is not configured on this local copy of Lumear, so verification links are saved to the application log instead of being sent to your inbox.</p>
            <p class="mx-auto mt-4 max-w-md rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">To receive real email, configure an SMTP or Resend account in <code>.env</code>. The project README includes the required settings.</p>
        @else
            <p class="mx-auto mt-4 max-w-md text-sm leading-6 text-maroon-900/65">We sent a secure verification link to your email address. Open it before publishing a seller store or using protected account actions.</p>
        @endif
        <form method="post" action="{{ route('verification.send') }}" class="mt-7">@csrf<button class="rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50">Send another link</button></form>
    </section>
@endsection
