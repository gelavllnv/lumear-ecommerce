@php
    $productImage = $product->images->first();
    $averageRating = $product->reviews_avg_rating;
    $promotion = $product->buyer_promotion;
@endphp

<article class="product-card">
    <a class="product-image" href="{{ route('products.show', $product) }}" aria-label="View {{ $product->name }}">
        @if($productImage)
            <img src="{{ route('media.show', ['path' => $productImage->path]) }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <span class="product-image-placeholder">Lumear</span>
        @endif

        @if($promotion['label'])
            <span class="product-badge product-promo-badge">{{ $promotion['label'] }}</span>
        @elseif($product->variants->count() > 1)
            <span class="product-badge">{{ $product->variants->count() }} options</span>
        @endif

        @if($promotion['flash_ends_at'])
            <span class="product-flash-timer" data-flash-countdown="{{ $promotion['flash_ends_at'] }}">Ends soon</span>
        @endif
    </a>

    <div class="product-content">
        <span class="product-seller">{{ $product->seller->name }}</span>
        <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>

        <div class="price-row {{ $promotion['flash_ends_at'] ? 'flash-price-row' : '' }}">
            <strong>₱{{ number_format($promotion['price_minor'] / 100, 2) }}</strong>
            @if($promotion['flash_ends_at'])<span class="flash-was-price">Was <del>₱{{ number_format($promotion['regular_price_minor'] / 100, 2) }}</del></span>@elseif($promotion['label'])<del>₱{{ number_format($promotion['regular_price_minor'] / 100, 2) }}</del>@endif
        </div>

        <div class="product-bottom">
            @if($averageRating)
                <span class="rating">★ {{ number_format($averageRating, 1) }} <small>· {{ $product->reviews_count }} reviews</small></span>
            @else
                <span class="rating">New listing</span>
            @endif
            <a class="add-cart-button" href="{{ route('products.show', $product) }}" aria-label="View {{ $product->name }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            </a>
        </div>
    </div>
</article>
