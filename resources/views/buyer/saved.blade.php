
@extends('layouts.buyer')

@section('title', 'Saved Products — Lumear')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/buyer/account-center.css') }}?v=1">
@endpush

@section('content')
<div class="lac-page" id="lumearSaved">
    <div class="lac-container">
        <nav class="lac-breadcrumb"><a href="{{ url('/home') }}">Home</a><span>/</span><span>Saved Products</span></nav>

        <div class="lac-heading">
            <div>
                <span class="lac-eyebrow">YOUR PERSONAL COLLECTION</span>
                <h1>Saved <em>Finds.</em></h1>
                <p>All the little things that caught your eye, in one place.</p>
            </div>
            <a class="lac-heading-link" href="{{ url('/search') }}">Explore More ↗</a>
        </div>

        <div class="lac-saved-topline">
            <div><span>♡</span><strong id="lacSavedCount">0</strong> products saved</div>
            <button type="button" id="lacClearSaved">Clear All</button>
        </div>

        <div class="lac-saved-grid" id="lacSavedGrid"></div>

        <div class="lac-order-empty" id="lacSavedEmpty" hidden>
            <div class="lac-empty-icon">♡</div>
            <span>YOUR WISHLIST IS WAITING</span>
            <h2>Nothing saved just yet.</h2>
            <p>See something you love? Tap the heart on a product to keep it here for later.</p>
            <a href="{{ url('/search') }}">Explore Products ↗</a>
        </div>
    </div>
    <div class="lac-toast" id="lacToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/buyer/account-center.js') }}?v=1" defer></script>
@endpush
