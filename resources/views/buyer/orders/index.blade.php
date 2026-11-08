
@extends('layouts.buyer')

@section('title', 'My Orders — Lumear')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/buyer/account-center.css') }}?v=2">
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

        @if(session('status'))
            <div class="lac-return-message" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->has('return'))
            <div class="lac-return-message lac-return-error" role="alert">{{ $errors->first('return') }}</div>
        @endif

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

        <section class="lac-order-main mt-8" aria-labelledby="returnsHeading">
            <div class="lac-returns-heading">
                <div>
                    <span class="lac-eyebrow">AFTER-SALES SUPPORT</span>
                    <h2 id="returnsHeading">Return a product</h2>
                    <p>Request a return after delivery. The seller reviews it before Logistics can schedule a rider pickup.</p>
                </div>
            </div>
            @foreach($orders as $order)
                @foreach($order->sellerOrders as $sellerOrder)
                    @foreach($sellerOrder->items as $item)
                        @php($returnRequest = $sellerOrder->returnRequests->where('order_item_id', $item->id)->sortByDesc('id')->first())
                        <article class="lac-return-item">
                            <h3>{{ $item->product_name }}</h3>
                            <p>Order {{ $order->reference }} · {{ $sellerOrder->seller->name }}</p>
                            @if($returnRequest && $returnRequest->status !== 'rejected')
                                <p class="lac-return-status">Return status: {{ str_replace('_', ' ', $returnRequest->status) }} · Pickup: {{ str_replace('_', ' ', $returnRequest->pickup_status) }}</p>
                            @elseif(in_array($sellerOrder->status, ['delivered', 'completed'], true))
                                <form class="lac-return-form" method="POST" action="{{ route('buyer.returns.store', $item) }}">
                                    @csrf
                                    <label>Reason<input name="reason" maxlength="100" required placeholder="e.g. Damaged item"></label>
                                    <label>Details<textarea name="description" minlength="10" maxlength="2000" required placeholder="Describe why you want to return this product."></textarea></label>
                                    <button type="submit">Request return</button>
                                </form>
                            @else
                                <p class="lac-return-muted">Return requests are available after delivery.</p>
                            @endif
                        </article>
                    @endforeach
                @endforeach
            @endforeach
        </section>

        <div class="lac-order-note">
            <span>✦</span>
            <p>Return requests are reviewed by the seller. Once approved, Logistics can assign a rider to pick up the item and confirm its return to the hub.</p>
        </div>
    </div>
    <div class="lac-toast" id="lacToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/buyer/account-center.js') }}?v=1" defer></script>
@endpush
