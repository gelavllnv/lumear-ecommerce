@extends('layouts.auth')
@section('title', 'Logistics application')
@section('content')
<div class="auth-card mx-auto max-w-lg rounded-[2rem] bg-cream-50 p-10 text-center rise"><p class="text-xs font-bold tracking-[.2em] text-maroon-700">LUMEAR LOGISTICS</p><h1 class="mt-4 font-display text-4xl">Application {{ ucfirst($provider?->status ?? 'pending') }}</h1>@if($provider?->status === 'approved')<a class="glow mt-8 inline-block rounded-xl bg-maroon-700 px-6 py-3 font-bold text-cream-50" href="{{ route('logistics.dashboard') }}">Open Logistics Centre</a>@elseif($provider?->status === 'rejected')<p class="mt-5 text-red-800">{{ $provider->rejection_reason }}</p>@else<p class="mt-5 text-sm text-maroon-900/65">Verify your email, then an administrator will review your logistics partner application.</p>@endif</div>
@endsection
