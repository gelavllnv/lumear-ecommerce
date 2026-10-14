
@php
    $imageUrl = 'https://images.unsplash.com/'.$product['image'].'?auto=format&fit=crop&w=650&q=85';
@endphp

<article
    class="product-card"
    data-id="{{ $product['id'] }}"
    data-name="{{ $product['name'] }}"
    data-seller="{{ $product['seller'] }}"
    data-price="{{ $product['price'] }}"
    data-image="{{ $imageUrl }}"
    data-stock="{{ $product['stock'] }}"
>
    <div class="product-image">
        <img src="{{ $imageUrl }}" alt="{{ $product['name'] }}" loading="lazy">

        <span class="product-badge {{ str_starts_with($product['badge'], '-') ? 'discount-badge' : '' }}">
            {{ $product['badge'] }}
        </span>

        <button class="wishlist-button" type="button" aria-label="Save {{ $product['name'] }}" aria-pressed="false">♡</button>
        <button class="quick-view-button" type="button">Quick View</button>
    </div>

    <div class="product-content">
        <span class="product-seller">{{ $product['seller'] }}</span>
        <h3>{{ $product['name'] }}</h3>

        <div class="price-row">
            <strong>₱{{ number_format($product['price']) }}</strong>
            @if($product['old'])
                <del>₱{{ number_format($product['old']) }}</del>
            @endif
        </div>

        <div class="product-bottom">
            <span class="rating">★ {{ number_format($product['rating'],1) }} <small>· Demo rating</small></span>
            <button class="add-cart-button" type="button" aria-label="Add {{ $product['name'] }} to cart">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
            </button>
        </div>
    </div>
</article>
