
@extends('layouts.buyer')

@section('title', 'My Orders — Lumear')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/buyer/account-center.css') }}?v=1">
@endpush

@section('content')
<div class="lac-page" id="lumearOrders">
    <div class="lac-container">
        <nav class="lac-breadcrumb"><a href="{{ url('/home') }}">Home</a><span>/</span><span>My Orders</span></nav>

        <div class="lac-heading">
            <div>
                <span class="lac-eyebrow">YOUR PURCHASE JOURNEY</span>
                <h1>My <em>Orders.</em></h1>
                <p>Everything you've ordered, all in one beautiful place.</p>
            </div>
            <a class="lac-heading-link" href="{{ url('/search') }}">Continue Shopping ↗</a>
        </div>

        <div class="lac-order-stats">
            <div><span>ALL ORDERS</span><strong id="lacOrdersAll">0</strong><small>Your complete history</small></div>
            <div><span>TO SHIP</span><strong id="lacOrdersShip">0</strong><small>Preparing your finds</small></div>
            <div><span>ON THE WAY</span><strong id="lacOrdersTransit">0</strong><small>Delivery in progress</small></div>
            <div><span>COMPLETED</span><strong id="lacOrdersDone">0</strong><small>Delivered purchases</small></div>
        </div>

        <div class="lac-order-main">
            <div class="lac-order-toolbar">
                <div class="lac-order-tabs" id="lacOrderTabs">
                    <button type="button" class="active" data-status="all">All Orders</button>
                    <button type="button" data-status="pending">To Pay</button>
                    <button type="button" data-status="processing">To Ship</button>
                    <button type="button" data-status="shipped">To Receive</button>
                    <button type="button" data-status="delivered">Completed</button>
                    <button type="button" data-status="cancelled">Cancelled</button>
                </div>
                <label class="lac-order-search">
                    <span>⌕</span>
                    <input type="search" id="lacOrderSearch" placeholder="Search order number or product">
                </label>
            </div>

            <div id="lacOrderList"></div>

            <div class="lac-order-empty" id="lacOrdersEmpty" hidden>
                <div class="lac-empty-icon">▣</div>
                <span>YOUR ORDER JOURNEY STARTS HERE</span>
                <h2 id="lacOrderEmptyTitle">No orders yet.</h2>
                <p id="lacOrderEmptyText">Once you complete checkout, your orders will appear here with their delivery status and details.</p>
                <a href="{{ url('/search') }}">Discover Products ↗</a>
            </div>
        </div>

        <div class="lac-order-note">
            <span>✦</span>
            <p>Order history is a demonstration until checkout and order management are connected to Laravel's database.</p>
        </div>
    </div>
    <div class="lac-toast" id="lacToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/buyer/account-center.js') }}?v=1" defer></script>
@endpush
