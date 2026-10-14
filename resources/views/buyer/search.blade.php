
@extends('layouts.buyer')

@section('title', 'Explore Products | LUMEAR')

@section('content')

@php
    $rawProducts = \App\Support\BuyerProducts::all();
    $rawCategories = \App\Support\BuyerCategories::all();

    $products = collect($rawProducts)->map(function ($product, $key) {
        if (is_object($product)) {
            $product = (array) $product;
        }

        if (!is_array($product)) {
            return null;
        }

        $name = $product['name']
            ?? $product['title']
            ?? 'Untitled Product';

        $slug = $product['slug']
            ?? (is_string($key) ? $key : \Illuminate\Support\Str::slug($name));

        $category = $product['category']
            ?? $product['category_name']
            ?? 'Lifestyle';

        if (is_array($category)) {
            $category = $category['name'] ?? 'Lifestyle';
        }

        $images = $product['images'] ?? [];
        $image = $product['image']
            ?? $product['thumbnail']
            ?? $product['photo']
            ?? ($images[0] ?? '');

        if (is_array($image)) {
            $image = $image['url'] ?? $image['src'] ?? '';
        }

        $image = (string) $image;

        if ($image !== '' && !preg_match('~^(https?://|/|data:)~i', $image)) {
            $image = asset(ltrim($image, '/'));
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'category' => (string) $category,
            'price' => (float) ($product['price'] ?? 0),
            'rating' => (float) ($product['rating'] ?? 4.5),
            'stock' => (int) ($product['stock'] ?? $product['available'] ?? 10),
            'seller' => (string) ($product['seller'] ?? $product['shop'] ?? 'Lumear Marketplace'),
            'image' => $image,
        ];
    })->filter()->values()->all();

    $categories = collect($rawCategories)->map(function ($category) {
        if (is_string($category)) {
            return $category;
        }

        if (is_object($category)) {
            $category = (array) $category;
        }

        return $category['name'] ?? $category['label'] ?? null;
    })->filter()->values()->all();
@endphp

<style>
    .lx-page {
        background: #faf7f2;
        min-height: 80vh;
        color: #342422;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .lx-page * {
        box-sizing: border-box;
    }

    .lx-container {
        max-width: 1390px;
        margin: auto;
        padding: 28px 30px 90px;
    }

    .lx-breadcrumb {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #97867b;
        font-size: 12px;
        margin-bottom: 32px;
    }

    .lx-breadcrumb a:hover {
        color: #873632;
    }

    .lx-heading {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 30px;
    }

    .lx-eyebrow {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .16em;
        color: #873632;
    }

    .lx-heading h1 {
        font-family: 'Fraunces', Georgia, serif;
        font-size: clamp(35px, 4vw, 52px);
        font-weight: 500;
        line-height: 1.2;
        margin: 9px 0;
        letter-spacing: -.04em;
    }

    .lx-heading h1 em {
        color: #873632;
    }

    .lx-heading p {
        color: #927e72;
        font-size: 12px;
        line-height: 1.8;
    }

    .lx-layout {
        display: grid;
        grid-template-columns: 250px minmax(0, 1fr);
        gap: 26px;
        align-items: start;
    }

    .lx-sidebar {
        background: #fffdf9;
        border: 1px solid #e8d9cc;
        border-radius: 12px;
        overflow: hidden;
    }

    .lx-sidebar-top {
        padding: 22px;
        background: #62191c;
        color: #fff;
    }

    .lx-sidebar-top span {
        font-size: 9px;
        letter-spacing: .16em;
        color: #e6c1b2;
        font-weight: 800;
    }

    .lx-sidebar-top h2 {
        margin-top: 8px;
        font-family: 'Fraunces', Georgia, serif;
        font-size: 25px;
    }

    .lx-filter-section {
        padding: 23px 20px;
        border-bottom: 1px solid #efe3d9;
    }

    .lx-filter-section h3 {
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 16px;
        color: #4c302b;
    }

    .lx-filter-list {
        display: flex;
        flex-direction: column;
        gap: 13px;
    }

    .lx-filter-list label,
    .lx-check {
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 11px;
        color: #7d6b60;
        cursor: pointer;
    }

    .lx-filter-list input,
    .lx-check input {
        accent-color: #873632;
    }

    .lx-price-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 9px;
    }

    .lx-price-fields input,
    .lx-filter-select {
        width: 100%;
        min-width: 0;
        height: 40px;
        padding: 0 10px;
        border: 1px solid #e3d0c1;
        border-radius: 6px;
        background: #fcf7f1;
        font-size: 11px;
        outline: none;
    }

    .lx-clear {
        width: calc(100% - 40px);
        margin: 20px;
        padding: 12px;
        border: 1px solid #873632;
        border-radius: 6px;
        color: #873632;
        font-size: 11px;
        font-weight: 800;
        background: transparent;
        cursor: pointer;
    }

    .lx-clear:hover {
        background: #f4e6db;
    }

    .lx-results {
        min-width: 0;
    }

    .lx-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        padding: 15px 18px;
        background: #fffdf9;
        border: 1px solid #e8d9cc;
        border-radius: 9px;
    }

    .lx-count {
        font-size: 12px;
        color: #8d786c;
    }

    .lx-count strong {
        color: #62191c;
    }

    .lx-toolbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .lx-toolbar-right label {
        font-size: 11px;
        color: #8d786c;
    }

    .lx-toolbar select {
        padding: 10px 12px;
        border: 1px solid #e2d1c4;
        border-radius: 6px;
        background: #faf5ef;
        color: #62191c;
        font-size: 11px;
        outline: none;
    }

    .lx-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .lx-card {
        background: #fffdf9;
        border: 1px solid #e9dbd0;
        border-radius: 11px;
        overflow: hidden;
        transition: transform .25s, box-shadow .25s;
    }

    .lx-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(65, 27, 25, .11);
    }

    .lx-card-photo {
        position: relative;
        display: block;
        aspect-ratio: 1 / 1;
        background: #f0e5da;
        overflow: hidden;
    }

    .lx-card-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .4s;
    }

    .lx-card:hover .lx-card-photo img {
        transform: scale(1.045);
    }

    .lx-photo-fallback {
        width: 100%;
        height: 100%;
        display: grid;
        place-items: center;
        color: #873632;
        font-size: 42px;
    }

    .lx-card-stock {
        position: absolute;
        left: 12px;
        top: 12px;
        padding: 7px 9px;
        background: #fffdf9ed;
        color: #62191c;
        font-size: 9px;
        font-weight: 800;
        border-radius: 5px;
    }

    .lx-card-body {
        padding: 17px;
    }

    .lx-card-category {
        color: #9b6558;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .09em;
    }

    .lx-card-title {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        min-height: 42px;
        margin: 10px 0;
        color: #382521;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.65;
    }

    .lx-card-title:hover {
        color: #873632;
    }

    .lx-card-seller {
        font-size: 10px;
        color: #9c8a7e;
        margin-bottom: 14px;
    }

    .lx-card-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 15px;
    }

    .lx-card-price {
        font-family: 'Fraunces', Georgia, serif;
        font-size: 23px;
        font-weight: 600;
        color: #62191c;
    }

    .lx-card-rating {
        color: #9a7960;
        font-size: 11px;
    }

    .lx-card-rating span {
        color: #b37b38;
    }

    .lx-view {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        padding: 12px 14px;
        background: #62191c;
        color: #fff;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 800;
    }

    .lx-view:hover {
        background: #873632;
    }

    .lx-empty {
        text-align: center;
        padding: 80px 20px;
        background: #fffdf9;
        border: 1px solid #e8d9cc;
        border-radius: 12px;
    }

    .lx-empty-icon {
        font-size: 45px;
        color: #873632;
    }

    .lx-empty h2 {
        font-family: 'Fraunces', Georgia, serif;
        font-size: 28px;
        margin: 16px 0 10px;
    }

    .lx-empty p {
        color: #938175;
        font-size: 12px;
        margin-bottom: 20px;
    }

    .lx-empty button {
        padding: 12px 20px;
        background: #62191c;
        border: 0;
        border-radius: 6px;
        color: white;
        font-size: 11px;
        font-weight: 800;
        cursor: pointer;
    }

    .lx-pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 30px;
    }

    .lx-pagination button {
        min-width: 37px;
        height: 37px;
        padding: 0 11px;
        border: 1px solid #e0cec0;
        border-radius: 6px;
        background: #fffdf9;
        color: #62191c;
        font-size: 11px;
        cursor: pointer;
    }

    .lx-pagination button.active {
        background: #62191c;
        color: #fff;
    }

    .lx-pagination button:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    @media (max-width: 1100px) {
        .lx-layout {
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 16px;
        }

        .lx-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 720px) {
        .lx-container {
            padding: 20px 15px 65px;
        }

        .lx-layout {
            grid-template-columns: 1fr;
        }

        .lx-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 11px;
        }

        .lx-card-body {
            padding: 12px;
        }

        .lx-card-price {
            font-size: 19px;
        }

        .lx-heading {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media (max-width: 420px) {
        .lx-grid {
            grid-template-columns: 1fr;
        }
    }


/* ==========================================
   LUMEAR — COMPACT PRODUCT CARDS
   ========================================== */

/* Display more products per row */
.lx-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

/* Smaller product cards */
.lx-card {
    border-radius: 9px;
}

/* Reduce the image height */
.lx-card-photo {
    aspect-ratio: 1 / 0.85;
}

/* Smaller stock badge */
.lx-card-stock {
    top: 9px;
    left: 9px;
    padding: 5px 8px;
    font-size: 8px;
}

/* Reduce spacing inside the card */
.lx-card-body {
    padding: 12px;
}

.lx-card-category {
    font-size: 8px;
}

.lx-card-title {
    min-height: 36px;
    margin: 7px 0;
    font-size: 11px;
    line-height: 1.5;
}

.lx-card-seller {
    font-size: 9px;
    margin-bottom: 10px;
}

/* Smaller price and rating */
.lx-card-bottom {
    margin-bottom: 11px;
}

.lx-card-price {
    font-size: 19px;
}

.lx-card-rating {
    font-size: 10px;
}

/* Compact View Product button */
.lx-view {
    padding: 10px 12px;
    font-size: 10px;
}

/* Responsive layout */
@media (max-width: 1200px) {
    .lx-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 850px) {
    .lx-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 480px) {
    .lx-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .lx-card-body {
        padding: 10px;
    }

    .lx-card-price {
        font-size: 17px;
    }

    .lx-card-title {
        font-size: 10px;
    }

    .lx-view {
        padding: 9px;
        font-size: 9px;
    }
}

</style>

<div class="lx-page" id="lumearExplore">
    <div class="lx-container">

        <nav class="lx-breadcrumb">
            <a href="{{ url('/home') }}">Home</a>
            <span>/</span>
            <span>Explore Products</span>
        </nav>

        <div class="lx-heading">
            <div>
                <span class="lx-eyebrow">THE LUMEAR COLLECTION</span>
                <h1>Explore <em>your next favorite.</em></h1>
                <p>Discover thoughtfully selected finds across every category.</p>
            </div>
        </div>

        <div class="lx-layout">

            <aside class="lx-sidebar">
                <div class="lx-sidebar-top">
                    <span>MAKE IT YOURS</span>
                    <h2>Filter Products</h2>
                </div>

                <div class="lx-filter-section">
                    <h3>Categories</h3>
                    <div class="lx-filter-list" id="lxCategoryFilters">
                        <label>
                            <input type="radio" name="lxCategory" value="" checked>
                            All Categories
                        </label>

                        @foreach ($categories as $category)
                            <label>
                                <input type="radio"
                                       name="lxCategory"
                                       value="{{ $category }}">
                                {{ $category }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="lx-filter-section">
                    <h3>Price Range</h3>
                    <div class="lx-price-fields">
                        <input type="number"
                               id="lxMinPrice"
                               min="0"
                               placeholder="Min ₱">

                        <input type="number"
                               id="lxMaxPrice"
                               min="0"
                               placeholder="Max ₱">
                    </div>
                </div>

                <div class="lx-filter-section">
                    <h3>Customer Rating</h3>
                    <select class="lx-filter-select" id="lxRating">
                        <option value="0">All Ratings</option>
                        <option value="4.5">4.5 Stars & Up</option>
                        <option value="4">4 Stars & Up</option>
                        <option value="3">3 Stars & Up</option>
                    </select>
                </div>

                <div class="lx-filter-section">
                    <h3>Availability</h3>
                    <label class="lx-check">
                        <input type="checkbox" id="lxInStock">
                        In Stock Only
                    </label>
                </div>

                <button type="button" class="lx-clear" id="lxClear">
                    Clear All Filters ↺
                </button>
            </aside>

            <section class="lx-results">

                <div class="lx-toolbar">
                    <div class="lx-count">
                        Showing <strong id="lxVisibleCount">0</strong>
                        of <strong id="lxTotalCount">{{ count($products) }}</strong> products
                    </div>

                    <div class="lx-toolbar-right">
                        <label for="lxSort">Sort by</label>

                        <select id="lxSort">
                            <option value="featured">Featured</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="rating">Highest Rated</option>
                            <option value="name">Name: A to Z</option>
                        </select>
                    </div>
                </div>

                <div class="lx-grid" id="lxProductGrid"></div>

                <div class="lx-empty" id="lxEmpty" hidden>
                    <div class="lx-empty-icon">✧</div>
                    <h2>No products found.</h2>
                    <p>Try another search or adjust your filters.</p>
                    <button type="button" id="lxResetEmpty">
                        Clear Filters
                    </button>
                </div>

                <div class="lx-pagination" id="lxPagination"></div>

            </section>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('lumearExplore');
    if (!root) return;

    const products = @json($products);
    const $ = selector => root.querySelector(selector);
    const $$ = selector => [...root.querySelectorAll(selector)];

    const perPage = 12;
    let currentPage = 1;

    const params = new URLSearchParams(window.location.search);
    const query = (params.get('q') || '').trim().toLowerCase();

    const money = value => '₱' + Number(value || 0).toLocaleString('en-PH');

    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char]));
    }

    function productUrl(slug) {
        return '/product/' + encodeURIComponent(String(slug));
    }

    function getFilteredProducts() {
        const category = $('input[name="lxCategory"]:checked')?.value || '';
        const min = $('#lxMinPrice').value === ''
            ? 0 : Number($('#lxMinPrice').value);
        const max = $('#lxMaxPrice').value === ''
            ? Infinity : Number($('#lxMaxPrice').value);
        const rating = Number($('#lxRating').value);
        const inStock = $('#lxInStock').checked;
        const sort = $('#lxSort').value;

        const filtered = products.filter(product => {
            const matchesQuery = !query || [
                product.name,
                product.category,
                product.seller
            ].some(value => String(value || '').toLowerCase().includes(query));

            const matchesCategory = !category ||
                String(product.category).toLowerCase() === category.toLowerCase();

            return matchesQuery &&
                matchesCategory &&
                product.price >= min &&
                product.price <= max &&
                product.rating >= rating &&
                (!inStock || product.stock > 0);
        });

        if (sort === 'price-low') {
            filtered.sort((a, b) => a.price - b.price);
        } else if (sort === 'price-high') {
            filtered.sort((a, b) => b.price - a.price);
        } else if (sort === 'rating') {
            filtered.sort((a, b) => b.rating - a.rating);
        } else if (sort === 'name') {
            filtered.sort((a, b) => a.name.localeCompare(b.name));
        }

        return filtered;
    }

    function render() {
        const filtered = getFilteredProducts();
        const totalPages = Math.ceil(filtered.length / perPage);

        if (currentPage > totalPages) {
            currentPage = Math.max(1, totalPages);
        }

        const start = (currentPage - 1) * perPage;
        const visible = filtered.slice(start, start + perPage);

        $('#lxTotalCount').textContent = filtered.length;
        $('#lxVisibleCount').textContent = visible.length;
        $('#lxEmpty').hidden = filtered.length !== 0;
        $('#lxProductGrid').hidden = filtered.length === 0;

        $('#lxProductGrid').innerHTML = visible.map(product => {
            const url = productUrl(product.slug);
            const image = product.image
                ? `<img src="${escapeHTML(product.image)}"
                        alt="${escapeHTML(product.name)}"
                        loading="lazy">`
                : `<div class="lx-photo-fallback">✦</div>`;

            return `
                <article class="lx-card">
                    <a class="lx-card-photo"
                       href="${escapeHTML(url)}"
                       aria-label="View ${escapeHTML(product.name)}">
                        ${image}
                        <span class="lx-card-stock">
                            ${product.stock > 0 ? 'IN STOCK' : 'SOLD OUT'}
                        </span>
                    </a>

                    <div class="lx-card-body">
                        <span class="lx-card-category">
                            ${escapeHTML(product.category)}
                        </span>

                        <a href="${escapeHTML(url)}"
                           class="lx-card-title">
                            ${escapeHTML(product.name)}
                        </a>

                        <p class="lx-card-seller">
                            Sold by ${escapeHTML(product.seller)}
                        </p>

                        <div class="lx-card-bottom">
                            <strong class="lx-card-price">
                                ${money(product.price)}
                            </strong>

                            <span class="lx-card-rating">
                                <span>★</span> ${Number(product.rating).toFixed(1)}
                            </span>
                        </div>

                        <a class="lx-view" href="${escapeHTML(url)}">
                            <span>View Product</span>
                            <span>↗</span>
                        </a>
                    </div>
                </article>
            `;
        }).join('');

        const pagination = $('#lxPagination');

        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let buttons = `
            <button type="button"
                    data-page="${currentPage - 1}"
                    ${currentPage === 1 ? 'disabled' : ''}>
                ←
            </button>
        `;

        for (let page = 1; page <= totalPages; page++) {
            buttons += `
                <button type="button"
                        data-page="${page}"
                        class="${page === currentPage ? 'active' : ''}">
                    ${page}
                </button>
            `;
        }

        buttons += `
            <button type="button"
                    data-page="${currentPage + 1}"
                    ${currentPage === totalPages ? 'disabled' : ''}>
                →
            </button>
        `;

        pagination.innerHTML = buttons;
    }

    function refresh() {
        currentPage = 1;
        render();
    }

    function clearFilters() {
        const all = $('input[name="lxCategory"][value=""]');
        if (all) all.checked = true;

        $('#lxMinPrice').value = '';
        $('#lxMaxPrice').value = '';
        $('#lxRating').value = '0';
        $('#lxInStock').checked = false;
        $('#lxSort').value = 'featured';

        if (query) {
            window.location.href = '/search';
            return;
        }

        refresh();
    }

    $$('input[name="lxCategory"]').forEach(input => {
        input.addEventListener('change', refresh);
    });

    ['#lxMinPrice', '#lxMaxPrice'].forEach(selector => {
        $(selector).addEventListener('input', refresh);
    });

    $('#lxRating').addEventListener('change', refresh);
    $('#lxInStock').addEventListener('change', refresh);
    $('#lxSort').addEventListener('change', refresh);

    $('#lxClear').addEventListener('click', clearFilters);
    $('#lxResetEmpty').addEventListener('click', clearFilters);

    $('#lxPagination').addEventListener('click', function (event) {
        const button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;

        currentPage = Number(button.dataset.page);
        render();

        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    render();
});
</script>
@endpush
