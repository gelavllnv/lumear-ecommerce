
@php
    $lumearCategories = [];

    foreach (\App\Support\BuyerCategories::all() as $key => $category) {
        $name = is_array($category)
            ? ($category['name'] ?? 'Category')
            : (string) $category;

        $slug = is_array($category)
            ? ($category['slug'] ?? \Illuminate\Support\Str::slug($name))
            : (is_string($key) ? $key : \Illuminate\Support\Str::slug($name));

        $lumearCategories[] = [
            'name' => $name,
            'slug' => $slug,
            'icon' => is_array($category)
                ? ($category['icon'] ?? '✦')
                : '✦'
        ];
    }
@endphp

<header class="lm-header"
    x-data="{
        accountOpen: false,
        categoryOpen: false,
        notificationOpen: false,
        mobileOpen: false
    }"
    @keydown.escape.window="
        accountOpen=false;
        categoryOpen=false;
        notificationOpen=false;
        mobileOpen=false
    ">

    {{-- UTILITY BAR --}}
    <div class="lm-utility">
        <div class="lm-shell lm-utility-inner">

            <div class="lm-utility-left">
                <a href="{{ url('/home') }}">Seller Centre</a>
                <a href="{{ url('/register') }}">Start Selling</a>
                <span class="lm-utility-separator"></span>
                <a href="{{ url('/home') }}">Discover more with Lumear</a>
            </div>

            <div class="lm-utility-right">

                <div class="lm-popover-holder">
                    <button type="button"
                        class="lm-utility-button"
                        @click="notificationOpen=!notificationOpen;accountOpen=false">
                        Notifications
                    </button>

                    <div class="lm-popover lm-notification-popover"
                        x-show="notificationOpen"
                        x-cloak
                        x-transition.opacity
                        @click.outside="notificationOpen=false">

                        <h3>Notifications</h3>
                        <p>Your order and delivery updates will appear here when the notification system is connected.</p>
                    </div>
                </div>

                <a href="{{ url('/chat') }}">Help</a>

                <span class="lm-language">English · PHP</span>

                <div class="lm-popover-holder">

                    <button type="button"
                        class="lm-account-button"
                        @click="accountOpen=!accountOpen;notificationOpen=false"
                        :aria-expanded="accountOpen.toString()">

                        <span class="lm-account-avatar">
                            <svg viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round">
                                <circle cx="12" cy="8" r="3.5"/>
                                <path d="M5.5 20c0-3.8 2.6-6 6.5-6s6.5 2.2 6.5 6"/>
                            </svg>
                        </span>

                        <span>My Account</span>

                        <svg class="lm-account-chevron"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    <div class="lm-popover lm-account-popover"
                        x-show="accountOpen"
                        x-cloak
                        x-transition.opacity
                        @click.outside="accountOpen=false">

                        <div class="lm-popover-title">MY LUMEAR ACCOUNT</div>

                        <a href="{{ url('/account') }}">My Profile</a>
                        <a href="{{ url('/orders') }}">My Orders</a>
                        <a href="{{ url('/saved') }}">Saved Products</a>
                        <a href="{{ url('/chat') }}">Messages</a>

                        <div class="lm-popover-divider"></div>

                        <a href="{{ url('/') }}">Sign Out</a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- MAIN MAROON NAVIGATION --}}
    <div class="lm-mainbar">
        <div class="lm-shell lm-mainbar-inner">

            {{-- ORIGINAL LUMEAR LOGO --}}
            <a href="{{ url('/home') }}" class="lm-brand">
                <img src="{{ asset('images/lumear-logo.png') }}"
                    alt="LUMEAR logo">

                <span>Lumear</span>
            </a>

            {{-- SEARCH --}}
            <div class="lm-search-column">

                <form action="{{ url('/search') }}"
                    method="GET"
                    class="lm-search-form">

                    <input
                        type="search"
                        name="q"
                        value="{{ request('q', '') }}"
                        placeholder="Search products, brands, and more..."
                        aria-label="Search products"
                    >

                    <button type="submit" aria-label="Search">
                        <svg viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round">
                            <circle cx="10.8" cy="10.8" r="6.8"/>
                            <path d="m16 16 4.5 4.5"/>
                        </svg>
                    </button>
                </form>

                <div class="lm-search-trending">
                    <span>Trending</span>
                    <a href="{{ url('/search?q=Skincare') }}">Skincare</a>
                    <a href="{{ url('/search?q=Coffee') }}">Coffee</a>
                    <a href="{{ url('/search?q=Keyboards') }}">Keyboards</a>
                    <a href="{{ url('/search?q=Home%20Decor') }}">Home Decor</a>
                </div>
            </div>

            {{-- ORDER / SAVED / CART --}}
            <nav class="lm-header-actions" aria-label="Buyer shortcuts">

                <a href="{{ url('/orders') }}" class="lm-header-action">
                    <span class="lm-action-icon">
                        <svg viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round">
                            <rect x="5" y="3" width="14" height="18" rx="2"/>
                            <path d="M9 8h6M9 12h6M9 16h4"/>
                        </svg>
                    </span>
                    <span class="lm-action-text">Orders</span>
                </a>

                <a href="{{ url('/saved') }}"
                    class="lm-header-action">

                    <span class="lm-action-icon">
                        <svg viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>
                        </svg>
                    </span>

                    <span class="lm-action-text">Saved</span>
                </a>

                <a href="{{ url('/cart') }}" class="lm-header-action">

                    <span class="lm-action-icon lm-cart-icon">
                        <svg viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M3 4h2l2.2 11.5h11.5L21 8H6"/>
                            <circle cx="9" cy="20" r="1"/>
                            <circle cx="18" cy="20" r="1"/>
                        </svg>

                        <span class="lm-cart-badge"
                            id="lumearNavCartCount"
                            hidden>0</span>
                    </span>

                    <span class="lm-action-text">Cart</span>
                </a>

            </nav>

            <button type="button"
                class="lm-mobile-menu-button"
                @click="mobileOpen=!mobileOpen"
                aria-label="Open navigation">

                <svg viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>

        </div>
    </div>

    {{-- CREAM CATEGORY NAVIGATION --}}
    <div class="lm-categorybar">
        <div class="lm-shell lm-categorybar-inner">

            <div class="lm-category-holder">

                <button type="button"
                    class="lm-category-button"
                    @click="categoryOpen=!categoryOpen"
                    :aria-expanded="categoryOpen.toString()">

                    <svg viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>

                    <span>ALL CATEGORIES</span>

                    <svg class="lm-category-chevron"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </button>

                <div class="lm-category-dropdown"
                    x-show="categoryOpen"
                    x-cloak
                    x-transition.opacity
                    @click.outside="categoryOpen=false">

                    <p>SHOP BY CATEGORY</p>

                    <div class="lm-category-grid">
                        @foreach($lumearCategories as $category)
                            <a href="{{ url('/category/'.$category['slug']) }}">
                                <span>{{ $category['icon'] }}</span>
                                {{ $category['name'] }}
                            </a>
                        @endforeach
                    </div>

                    <a href="{{ url('/search') }}"
                        class="lm-view-all-categories">
                        Explore All Products →
                    </a>
                </div>

            </div>

            <div class="lm-category-links">
                <a href="{{ url('/search') }}">Flash Deals</a>
                <a href="{{ url('/search?sort=rating') }}">Recommended</a>
                <a href="{{ url('/search') }}">Collections</a>
            </div>

            <div class="lm-category-account-links">
                <a href="{{ url('/orders') }}">My Orders</a>
                <a href="{{ url('/chat') }}">Messages</a>
                <a href="{{ url('/account') }}">My Profile</a>
            </div>

        </div>
    </div>

    {{-- MOBILE MENU --}}
    <div class="lm-mobile-panel"
        x-show="mobileOpen"
        x-cloak>

        <a href="{{ url('/home') }}">Home</a>
        <a href="{{ url('/search') }}">Shop Products</a>
        <a href="{{ url('/orders') }}">My Orders</a>
        <a href="{{ url('/saved') }}">Saved Products</a>
        <a href="{{ url('/cart') }}">Shopping Cart</a>
        <a href="{{ url('/account') }}">My Account</a>
    </div>

</header>
