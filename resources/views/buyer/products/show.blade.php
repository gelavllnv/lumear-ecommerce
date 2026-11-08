
@extends('layouts.buyer')

@php
    $slug = request()->route('slug');

    $catalogProduct = collect(\App\Support\BuyerProducts::all())
        ->firstWhere('slug', $slug);

    $product = $product ?? $catalogProduct;

    abort_if(!$product, 404);

    $categorySlug = $product['category'] ?? 'home-and-garden';

    $category = collect(\App\Support\BuyerCategories::all())
        ->firstWhere('slug', $categorySlug);

    $categoryName = $category['name']
        ?? ucwords(str_replace('-', ' ', $categorySlug));

    $subcategory = collect($category['subcategories'] ?? [])
        ->firstWhere('slug', $product['subcategory'] ?? '');

    $subcategoryName = $subcategory['name'] ?? $categoryName;

    $name = $product['name'];

    $price = (float) str_replace(',', '', (string) ($product['price'] ?? 0));

    $oldPrice = (float) str_replace(',', '', (string) ($product['old_price'] ?? 0));

    $rating = (float) ($product['rating'] ?? 4.8);

    $stock = (int) ($product['stock'] ?? 24);

    $sold = $product['sold'] ?? '100+';

    $description = $product['description']
        ?? "Explore this thoughtfully selected {$subcategoryName} item. Designed for everyday use and chosen for its practical style and quality. Product specifications and seller information are demonstration data until the seller catalog is connected.";

    $colors = $product['colors'] ?? ['Classic', 'Natural', 'Dark'];

    $sizes = $product['sizes'] ?? ['Standard', 'Large'];

    $related = collect(\App\Support\BuyerProducts::forCategory($categorySlug))
        ->reject(fn ($item) => $item['slug'] === ($product['slug'] ?? $slug))
        ->take(4)
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Demo Product Photography
    |--------------------------------------------------------------------------
    | Photos are loaded from Unsplash.
    | No need to download images or install packages.
    */

    $photoSets = [
        'pet-supplies' => [
            'photo-1583511655857-d19b40a7a54e',
            'photo-1548199973-03cce0bbc87b',
            'photo-1601758228041-f3b2795255f1'
        ],
        'kids-and-baby' => [
            'photo-1515488042361-ee00e0ddd4e4',
            'photo-1503454537195-1dcabb73ffb9',
            'photo-1484820540004-14229fe36ca4'
        ],
        'electronics-and-gadgets' => [
            'photo-1498049794561-7780e7231661',
            'photo-1519389950473-47ba0277781c',
            'photo-1523275335684-37898b6baf30'
        ],
        'home-and-garden' => [
            'photo-1600210492486-724fe5c67fb0',
            'photo-1616486338812-3dadae4b4ace',
            'photo-1600607687939-ce8a6c25118c'
        ],
        'womens-apparel' => [
            'photo-1483985988355-763728e1935b',
            'photo-1485968579580-b6d095142e6e',
            'photo-1490481651871-ab68de25d43d'
        ],
        'mens-apparel' => [
            'photo-1617127365659-c47fa864d8bc',
            'photo-1488161628813-04466f872be2',
            'photo-1512436991641-6745cdb1723f'
        ],
        'sports-and-outdoors' => [
            'photo-1517836357463-d25dfeac3438',
            'photo-1571019613454-1cb2f99b2d8b',
            'photo-1534438327276-14e5300c3a48'
        ],
        'health-and-beauty' => [
            'photo-1596462502278-27bfdc403348',
            'photo-1522335789203-aabd1fc54bc9',
            'photo-1620916566398-39f1143ab7be'
        ],
        'books-and-media' => [
            'photo-1495446815901-a7297e633e8d',
            'photo-1512820790803-83ca734da794',
            'photo-1481627834876-b7833e8f5570'
        ],
        'food-and-gourmet' => [
            'photo-1540189549336-e6e99c3679fe',
            'photo-1490645935967-10de6ba17061',
            'photo-1504674900247-0877df9cc836'
        ],
    ];

    $photoIds = $photoSets[$categorySlug]
        ?? [
            'photo-1494438639946-1ebd1d20bf85',
            'photo-1498050108023-c5249f4df085',
            'photo-1523275335684-37898b6baf30'
        ];

    $gallery = collect($photoIds)
        ->map(fn ($id) => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w=1100&q=85")
        ->values()
        ->all();

    $discount = $oldPrice > $price && $oldPrice > 0
        ? round((($oldPrice - $price) / $oldPrice) * 100)
        : 0;

    $productData = [
        'id' => $product['slug'] ?? $slug,
        'slug' => $product['slug'] ?? $slug,
        'name' => $name,
        'price' => $price,
        'image' => $gallery[0],
        'stock' => $stock,
        'seller' => 'Lumear Marketplace',
        'category' => $categorySlug
    ];
@endphp

@section('title', $name . ' — LUMEAR')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/buyer/product-details.css') }}?v=2">
@endpush

@section('content')

<div
    class="lumear-pdp"
    id="lumearProduct"
    data-product="{{ json_encode($productData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
>

    <div class="pdp-container">

        {{-- BREADCRUMBS --}}
        <nav class="pdp-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ url('/home') }}">Home</a>
            <span>/</span>
            <a href="{{ url('/category/' . $categorySlug) }}">
                {{ $categoryName }}
            </a>
            <span>/</span>
            <span>{{ $name }}</span>
        </nav>

        {{-- PRODUCT MAIN SECTION --}}
        <div class="pdp-main">

            {{-- LEFT: PRODUCT GALLERY --}}
            <div class="pdp-gallery">

                <div class="pdp-main-photo">

                    <img
                        id="pdpMainImage"
                        src="{{ $gallery[0] }}"
                        alt="{{ $name }}"
                    >

                    <div class="pdp-photo-top">
                        <span class="pdp-photo-label">
                            LUMEAR SELECTION
                        </span>

                        @if($discount > 0)
                            <span class="pdp-discount-label">
                                -{{ $discount }}%
                            </span>
                        @endif
                    </div>

                    <button
                        type="button"
                        class="pdp-zoom-button"
                        id="pdpZoom"
                        aria-label="Enlarge product photo"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round"
                             stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-4-4M8 11h6M11 8v6"></path>
                        </svg>
                    </button>

                    <div class="pdp-image-counter">
                        <span id="pdpImageCounter">01</span>
                        <span>/</span>
                        <span>{{ count($gallery) < 10 ? '0' : '' }}{{ count($gallery) }}</span>
                    </div>

                </div>

                <div class="pdp-thumbnails" id="pdpThumbnails">
                    @foreach($gallery as $image)
                        <button
                            type="button"
                            class="pdp-thumbnail {{ $loop->first ? 'active' : '' }}"
                            data-index="{{ $loop->index }}"
                            aria-label="View product photo {{ $loop->iteration }}"
                        >
                            <img
                                src="{{ $image }}"
                                alt="{{ $name }} — photo {{ $loop->iteration }}"
                            >
                        </button>
                    @endforeach
                </div>

                <div class="pdp-gallery-note">
                    <span>✦</span>
                    <p>Discover details worth looking closer at.</p>
                </div>

            </div>

            {{-- RIGHT: PRODUCT INFORMATION --}}
            <div class="pdp-info">

                <div class="pdp-product-intro">
                    <div class="pdp-eyebrow-row">
                        <span class="pdp-eyebrow">
                            {{ strtoupper($subcategoryName) }}
                        </span>
                        <span class="pdp-availability">
                            <i></i>
                            {{ $stock > 0 ? 'In Stock' : 'Out of Stock' }}
                        </span>
                    </div>

                    <h1>{{ $name }}</h1>

                    <div class="pdp-rating-line">
                        <span class="pdp-stars">★★★★★</span>
                        <strong>{{ number_format($rating, 1) }}</strong>
                        <span>Sample product rating</span>
                        <span class="pdp-rating-divider"></span>
                        <span>{{ $sold }} sold</span>
                    </div>
                </div>

                <div class="pdp-price-panel">
                    <span class="pdp-price-caption">YOUR PRICE</span>

                    <div class="pdp-price-row">
                        <strong>₱{{ number_format($price, 2) }}</strong>

                        @if($oldPrice > $price)
                            <del>₱{{ number_format($oldPrice, 2) }}</del>
                            <span class="pdp-price-off">
                                SAVE {{ $discount }}%
                            </span>
                        @endif
                    </div>

                    <p>Thoughtful finds for the way you live.</p>
                </div>

                <p class="pdp-short-description">
                    {{ $description }}
                </p>

                {{-- COLOR VARIATIONS --}}
                <div class="pdp-option-block">
                    <div class="pdp-option-title">
                        <strong>Choose a finish</strong>
                        <span id="pdpSelectedColor">
                            {{ $colors[0] ?? 'Classic' }}
                        </span>
                    </div>

                    <div class="pdp-options" id="pdpColors">
                        @foreach($colors as $color)
                            <button
                                type="button"
                                class="pdp-option {{ $loop->first ? 'active' : '' }}"
                                data-value="{{ $color }}"
                            >
                                {{ $color }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- SIZE VARIATIONS --}}
                <div class="pdp-option-block">
                    <div class="pdp-option-title">
                        <strong>Select option</strong>
                        <span id="pdpSelectedSize">
                            {{ $sizes[0] ?? 'Standard' }}
                        </span>
                    </div>

                    <div class="pdp-options" id="pdpSizes">
                        @foreach($sizes as $size)
                            <button
                                type="button"
                                class="pdp-option {{ $loop->first ? 'active' : '' }}"
                                data-value="{{ $size }}"
                            >
                                {{ $size }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- QUANTITY --}}
                <div class="pdp-quantity-section">
                    <div>
                        <strong>Quantity</strong>
                        <span>{{ $stock }} pieces available</span>
                    </div>

                    <div class="pdp-quantity-control">
                        <button type="button" id="pdpDecrease" aria-label="Decrease quantity">−</button>
                        <span id="pdpQuantity">1</span>
                        <button type="button" id="pdpIncrease" aria-label="Increase quantity">+</button>
                    </div>
                </div>

                {{-- ACTIONS --}}
                <div class="pdp-actions">

                    <button
                        type="button"
                        class="pdp-add-cart"
                        id="pdpAddCart"
                        {{ $stock < 1 ? 'disabled' : '' }}
                    >
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"></path>
                        </svg>
                        Add to Cart
                    </button>

                    <button
                        type="button"
                        class="pdp-buy-now"
                        id="pdpBuyNow"
                        {{ $stock < 1 ? 'disabled' : '' }}
                    >
                        Buy Now
                        <span>↗</span>
                    </button>

                </div>

                <button type="button" class="pdp-save-button" id="pdpSave">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"></path>
                    </svg>
                    <span>Save to Favorites</span>
                </button>

                {{-- BUYER BENEFITS --}}
                <div class="pdp-benefits">

                    <div>
                        <span>◇</span>
                        <strong>Secure checkout</strong>
                        <small>Payment options at checkout</small>
                    </div>

                    <div>
                        <span>▣</span>
                        <strong>Order tracking</strong>
                        <small>Follow every delivery stage</small>
                    </div>

                    <div>
                        <span>↺</span>
                        <strong>Customer support</strong>
                        <small>Help when you need it</small>
                    </div>

                </div>

                {{-- SELLER INFORMATION --}}
                <div class="pdp-seller">
                    <div class="pdp-seller-icon">L</div>

                    <div>
                        <small>DEMONSTRATION STORE</small>
                        <strong>Lumear Marketplace</strong>
                        <span>Store details will be linked to verified sellers.</span>
                    </div>

                    <a href="{{ url('/chat') }}">Message ↗</a>
                </div>

            </div>
        </div>

        {{-- PRODUCT DETAILS --}}
        <div class="pdp-details" id="pdpDetails">

            <div class="pdp-section-heading">
                <span>THE FINER DETAILS</span>
                <h2>Made for <em>your everyday.</em></h2>
            </div>

            <div class="pdp-tab-bar" role="tablist">
                <button type="button" class="active" data-tab="description">
                    Overview
                </button>

                <button type="button" data-tab="specifications">
                    Specifications
                </button>

                <button type="button" data-tab="reviews">
                    Ratings & Reviews
                </button>
            </div>

            <div class="pdp-tab-content active" data-panel="description">
                <div>
                    <h3>A little more about this find.</h3>
                    <p>{{ $description }}</p>
                </div>

                <div class="pdp-detail-highlight">
                    <span>✳</span>
                    <strong>Selected with intention.</strong>
                    <p>
                        Every category is designed to help you discover
                        something worth bringing home.
                    </p>
                </div>
            </div>

            <div class="pdp-tab-content" data-panel="specifications">
                <div class="pdp-spec-grid">

                    <div>
                        <span>Category</span>
                        <strong>{{ $categoryName }}</strong>
                    </div>

                    <div>
                        <span>Product type</span>
                        <strong>{{ $subcategoryName }}</strong>
                    </div>

                    <div>
                        <span>Available options</span>
                        <strong>{{ implode(', ', $sizes) }}</strong>
                    </div>

                    <div>
                        <span>Available finishes</span>
                        <strong>{{ implode(', ', $colors) }}</strong>
                    </div>

                    <div>
                        <span>Stock (demo)</span>
                        <strong>{{ $stock }} pieces</strong>
                    </div>

                    <div>
                        <span>Product reference</span>
                        <strong>{{ $product['slug'] ?? $slug }}</strong>
                    </div>

                </div>
            </div>

            <div class="pdp-tab-content" data-panel="reviews">
                <div class="pdp-review-placeholder">
                    <span>★★★★★</span>
                    <h3>Customer stories, coming soon.</h3>
                    <p>
                        This catalog currently uses sample ratings.
                        Verified customer reviews will appear once
                        orders and reviews are connected to the backend.
                    </p>
                </div>
            </div>

        </div>

        {{-- RELATED PRODUCTS --}}
        @if($related->isNotEmpty())
            <section class="pdp-related">

                <div class="pdp-related-header">
                    <div>
                        <span>DISCOVER MORE</span>
                        <h2>Keep exploring <em>the collection.</em></h2>
                    </div>

                    <a href="{{ url('/category/' . $categorySlug) }}">
                        Explore category ↗
                    </a>
                </div>

                <div class="pdp-related-grid">

                    @foreach($related as $item)
                        <a
                            href="{{ url('/product/' . $item['slug']) }}"
                            class="pdp-related-card"
                        >
                            <div class="pdp-related-photo">
                                <img
                                    src="{{ $gallery[$loop->index % count($gallery)] }}"
                                    alt="{{ $item['name'] }}"
                                    loading="lazy"
                                >

                                <span>DISCOVER ↗</span>
                            </div>

                            <div class="pdp-related-body">
                                <small>{{ $subcategoryName }}</small>

                                <strong>{{ $item['name'] }}</strong>

                                <div>
                                    <b>
                                        ₱{{ number_format((float) str_replace(',', '', $item['price']), 2) }}
                                    </b>

                                    <span>★ {{ $item['rating'] ?? '4.8' }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach

                </div>

            </section>
        @endif

    </div>

    {{-- TOAST --}}
    <div class="pdp-toast" id="pdpToast" role="status" aria-live="polite"></div>

    {{-- PHOTO LIGHTBOX --}}
    <div class="pdp-lightbox" id="pdpLightbox" aria-hidden="true">
        <button
            type="button"
            id="pdpCloseLightbox"
            aria-label="Close enlarged photo"
        >
            ×
        </button>

        <img id="pdpLightboxImage" alt="Enlarged product photo">
    </div>

</div>

@endsection

@push('scripts')
<script>
    window.lumearPdpGallery = @json($gallery);
</script>

<script src="{{ asset('js/buyer/product-details.js') }}?v=2" defer></script>
@endpush
