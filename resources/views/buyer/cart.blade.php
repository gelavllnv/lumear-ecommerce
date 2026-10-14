
@extends('layouts.buyer')

@section('title', 'My Shopping Cart — LUMEAR')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/buyer/cart.css') }}?v=1">
@endpush

@section('content')

<div class="lm-cart-page" id="lumearCartPage">

    <div class="lm-cart-container">

        {{-- BREADCRUMBS --}}
        <nav class="lm-cart-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ url('/home') }}">Home</a>
            <span>/</span>
            <span>Shopping Cart</span>
        </nav>

        {{-- PAGE HEADING --}}
        <div class="lm-cart-heading">
            <div>
                <span class="lm-cart-eyebrow">
                    YOUR SHOPPING BAG
                </span>

                <h1>
                    Your cart, <em>your finds.</em>
                </h1>

                <p>
                    Review your favorites before checking out.
                </p>
            </div>

            <a href="{{ url('/search') }}" class="lm-cart-continue">
                <span>←</span>
                Continue Shopping
            </a>
        </div>

        {{-- CART CONTENT --}}
        <div class="lm-cart-layout" id="cartContent" hidden>

            <div class="lm-cart-left">

                {{-- SELECT ALL --}}
                <div class="lm-cart-toolbar">
                    <label class="lm-cart-check-label">
                        <input
                            type="checkbox"
                            id="cartSelectAll"
                            aria-label="Select all cart items"
                        >

                        <span class="lm-cart-checkbox"></span>

                        <strong>
                            Select All
                            <span id="cartAllCount">(0 items)</span>
                        </strong>
                    </label>

                    <button
                        type="button"
                        id="cartRemoveSelected"
                        class="lm-cart-remove-selected"
                    >
                        Remove Selected
                    </button>
                </div>

                {{-- GROUPED SHOP ITEMS --}}
                <div id="cartShopGroups" class="lm-cart-shop-groups"></div>

                {{-- CART FOOTNOTE --}}
                <div class="lm-cart-footnote">
                    <span>✦</span>

                    <div>
                        <strong>Good finds deserve a second look.</strong>
                        <p>
                            Review product options and quantities before
                            continuing to checkout.
                        </p>
                    </div>
                </div>

            </div>

            {{-- ORDER SUMMARY --}}
            <aside class="lm-cart-summary">

                <div class="lm-cart-summary-card">

                    <div class="lm-cart-summary-top">
                        <span>YOUR ORDER</span>
                        <h2>Order Summary</h2>
                        <p>
                            A quick look at your selected items.
                        </p>
                    </div>

                    <div class="lm-cart-voucher">

                        <label for="cartVoucher">
                            HAVE A VOUCHER?
                        </label>

                        <div class="lm-cart-voucher-row">
                            <input
                                type="text"
                                id="cartVoucher"
                                placeholder="Enter voucher code"
                                autocomplete="off"
                            >

                            <button
                                type="button"
                                id="cartApplyVoucher"
                            >
                                Apply
                            </button>
                        </div>

                        <p id="cartVoucherMessage" aria-live="polite"></p>

                    </div>

                    <div class="lm-cart-summary-lines">

                        <div>
                            <span>
                                Subtotal
                                <small id="cartSelectedCount">(0 items)</small>
                            </span>

                            <strong id="cartSubtotal">₱0.00</strong>
                        </div>

                        <div>
                            <span>Estimated Shipping</span>
                            <strong id="cartShipping">₱0.00</strong>
                        </div>

                        <div class="lm-cart-discount-line">
                            <span>Voucher Discount</span>
                            <strong id="cartDiscount">−₱0.00</strong>
                        </div>

                    </div>

                    <div class="lm-cart-summary-total">
                        <span>Estimated Total</span>
                        <strong id="cartTotal">₱0.00</strong>
                    </div>

                    <button
                        type="button"
                        class="lm-cart-checkout"
                        id="cartCheckout"
                    >
                        Proceed to Checkout
                        <span>↗</span>
                    </button>

                    <p class="lm-cart-checkout-note">
                        Address, shipping, and payment will be
                        confirmed at checkout.
                    </p>

                    <div class="lm-cart-summary-benefits">

                        <div>
                            <span>◇</span>
                            <p>Secure Checkout</p>
                        </div>

                        <div>
                            <span>▣</span>
                            <p>Order Tracking</p>
                        </div>

                        <div>
                            <span>✦</span>
                            <p>Customer Support</p>
                        </div>

                    </div>

                </div>

            </aside>

        </div>

        {{-- EMPTY CART --}}
        <div class="lm-cart-empty" id="cartEmpty" hidden>

            <div class="lm-cart-empty-art">
                <svg viewBox="0 0 120 120" fill="none" aria-hidden="true">
                    <path d="M25 43h70l-7 55H32l-7-55Z"
                          stroke="currentColor" stroke-width="3"
                          stroke-linejoin="round"/>
                    <path d="M43 46V33c0-10 7-18 17-18s17 8 17 18v13"
                          stroke="currentColor" stroke-width="3"
                          stroke-linecap="round"/>
                    <path d="m48 73 8 8 17-19"
                          stroke="currentColor" stroke-width="3"
                          stroke-linecap="round"
                          stroke-linejoin="round"/>
                </svg>
            </div>

            <span>NOTHING IN YOUR BAG YET</span>

            <h2>
                Your next favorite <em>is waiting.</em>
            </h2>

            <p>
                Your shopping cart is empty. Discover something
                lovely and add it to your bag.
            </p>

            <a href="{{ url('/search') }}">
                Explore Products ↗
            </a>

        </div>

        {{-- RECOMMENDATION CTA --}}
        <section class="lm-cart-bottom-banner">

            <div>
                <span>MORE TO DISCOVER</span>
                <h2>There's always something <em>worth finding.</em></h2>
                <p>Explore thoughtful picks across all your favorite categories.</p>
            </div>

            <a href="{{ url('/search') }}">
                Discover More ↗
            </a>

        </section>

    </div>

    {{-- REMOVE CONFIRMATION MODAL --}}
    <div
        class="lm-cart-modal-backdrop"
        id="cartRemoveModal"
        hidden
    >
        <div
            class="lm-cart-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cartModalTitle"
        >
            <div class="lm-cart-modal-icon">×</div>

            <h3 id="cartModalTitle">Remove this item?</h3>

            <p id="cartModalDescription">
                This item will be removed from your shopping cart.
            </p>

            <div class="lm-cart-modal-actions">
                <button
                    type="button"
                    id="cartCancelRemove"
                    class="lm-cart-modal-cancel"
                >
                    Keep Item
                </button>

                <button
                    type="button"
                    id="cartConfirmRemove"
                    class="lm-cart-modal-confirm"
                >
                    Remove
                </button>
            </div>
        </div>
    </div>

    {{-- TOAST --}}
    <div
        class="lm-cart-toast"
        id="cartToast"
        role="status"
        aria-live="polite"
    ></div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/buyer/cart.js') }}?v=1" defer></script>
@endpush
