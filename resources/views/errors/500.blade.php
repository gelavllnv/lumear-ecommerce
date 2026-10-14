@extends('layouts.app')

@section('title', 'Temporary problem — Lumear')

@section('content')
    <section class="mx-auto max-w-xl rounded-2xl bg-cream-50 p-8 text-center shadow-soft"><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Temporary problem</p><h1 class="mt-3 font-display text-4xl">Something did not complete.</h1><p class="mt-4 text-sm leading-6 text-maroon-900/65">No payment was taken. Please return to Lumear and try again in a moment.</p><a class="mt-6 inline-block rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50" href="{{ route('shop') }}">Return to marketplace</a></section>
@endsection
