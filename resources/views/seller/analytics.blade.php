@extends('layouts.seller')
@section('seller-content')
<div class="seller-page seller-analytics-page">
    <header class="seller-page-header">
        <div><p class="seller-page-eyebrow">Business intelligence</p><h1>Analytics</h1><p>Use orders, earnings, and inventory trends to guide your next move.</p></div>
        <a class="seller-page-action" href="{{ route('seller.earnings') }}">View earnings <span>→</span></a>
    </header>

    <section class="seller-metric-grid seller-insight-grid">
        <article class="seller-surface"><p class="seller-page-eyebrow">Sales</p><strong>Order insights</strong><span>See order-level sales and product earnings.</span></article>
        <article class="seller-surface"><p class="seller-page-eyebrow">Inventory</p><strong>Stock focus</strong><span>Review low-stock variants before they sell out.</span></article>
        <article class="seller-surface"><p class="seller-page-eyebrow">Customers</p><strong>Messages &amp; reviews</strong><span>Keep product questions and feedback in one place.</span></article>
    </section>

    <section class="seller-operation-card seller-analytics-actions">
        <p class="seller-page-eyebrow">Performance workspace</p><h2>Build from live marketplace activity.</h2>
        <p class="seller-analytics-copy">Sales analytics use completed seller orders. Open Earnings to filter sales by product and date, Inventory to review stock, and Messages to respond to buyer questions.</p>
        <div class="seller-action-row"><a class="seller-page-action" href="{{ route('seller.earnings') }}">Earnings report <span>→</span></a><a class="seller-outline-action" href="{{ route('seller.inventory') }}">Review inventory</a><a class="seller-outline-action" href="{{ route('seller.messages') }}">Open messages</a></div>
    </section>

    <section class="seller-operation-card seller-live-performance">
        <p class="seller-page-eyebrow">Live performance</p>
        <div class="seller-live-stats"><div><strong>{{ $orders->count() }}</strong><span>fulfilled orders</span></div><div><strong>{{ $repeatBuyers }}</strong><span>repeat buyers</span></div><div><strong>₱{{ number_format($promotionImpact/100, 2) }}</strong><span>offer savings</span></div></div>
        <h2>Best-selling products</h2>
        <div class="seller-best-sellers">@forelse($bestSellers as $product)<div><span>{{ $product['name'] }} <small>· {{ $product['quantity'] }} sold</small></span><b>₱{{ number_format($product['revenue_minor']/100, 2) }}</b></div>@empty <p>No fulfilled sales yet.</p>@endforelse</div>
    </section>
</div>
@endsection
