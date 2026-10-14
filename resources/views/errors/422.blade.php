@extends('layouts.app')

@section('title', 'Action needs attention — Lumear')

@section('content')
    <section class="mx-auto max-w-xl rounded-2xl bg-cream-50 p-8 text-center shadow-soft"><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Action needs attention</p><h1 class="mt-3 font-display text-4xl">We could not complete that request.</h1><p class="mt-4 text-sm leading-6 text-maroon-900/65">Check that the required details are complete and current, then try again.</p><a class="mt-6 inline-block rounded-xl bg-maroon-700 px-5 py-3 text-sm font-bold text-cream-50" href="{{ url()->previous() }}">Go back</a></section>
@endsection
