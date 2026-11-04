<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Lumear Logistics')</title>

    {{-- Saved theme --}}
    <script>
        (function () {
            try {
                const savedTheme = localStorage.getItem(
                    'lumear_logistics_theme'
                );

                document.documentElement.setAttribute(
                    'data-theme',
                    savedTheme === 'light' ? 'light' : 'dark'
                );
            } catch (error) {
                document.documentElement.setAttribute(
                    'data-theme',
                    'dark'
                );
            }
        })();
    </script>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    {{-- Existing dashboard stylesheet --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/logistics/dashboard.css') }}"
    >

    <style>
        .logistics-logout-form,
        .logistics-profile-logout-form {
            width: 100%;
            margin: 0;
        }

        .logistics-logout-button {
            width: 100%;
            font: inherit;
            text-align: left;
            cursor: pointer;
        }

        .logistics-profile-logout-button {
            display: block;
            width: 100%;
            padding: 12px 20px;
            border: 0;
            background: transparent;
            color: #d78e94;
            font: inherit;
            font-size: 12px;
            text-align: left;
            cursor: pointer;
        }

        .logistics-profile-logout-button:hover {
            background: rgba(135, 54, 50, .12);
        }

        .center-summary-copy strong,
        .profile-copy strong {
            overflow-wrap: anywhere;
        }

        .logistics-empty-notification {
            padding: 22px 18px;
            text-align: center;
            font-size: 12px;
            opacity: .75;
        }

        .logistics-center-location {
            display: block;
            margin-top: 5px;
            font-size: 10px;
            opacity: .7;
        }

        .logistics-profile-owner {
            display: block;
            padding: 12px 20px;
            font-size: 11px;
            opacity: .75;
            overflow-wrap: anywhere;
        }

        .topbar-search input:focus {
            outline: none;
        }
    </style>

    @stack('styles')
</head>

<body>

@php
    /*
    |--------------------------------------------------------------------------
    | CURRENT AUTHENTICATED LOGISTICS PROVIDER
    |--------------------------------------------------------------------------
    |
    | The layout is shared by all Logistics pages.
    | Fetch the provider belonging to the authenticated user,
    | instead of displaying hardcoded demonstration information.
    |
    | The Logistics routes should remain protected by
    | EnsureApprovedLogistics middleware.
    |
    */

    $logisticsUser = auth()->user();

    $layoutProvider = null;

    if ($logisticsUser) {
        $layoutProvider = \Illuminate\Support\Facades\DB::table(
            'logistics_providers'
        )
            ->where('user_id', $logisticsUser->id)
            ->first();
    }

    $logisticsBusinessName = $layoutProvider->name
        ?? 'Logistics Provider';

    $logisticsMunicipality = $layoutProvider->municipality_name
        ?? 'Municipality not set';

    $logisticsProvince = $layoutProvider->province_name
        ?? 'Province not set';

    $logisticsStatus = $layoutProvider->status
        ?? 'pending';

    $logisticsOwnerName = $logisticsUser->name
        ?? 'Logistics Operator';

    $logisticsInitials = collect(
        preg_split('/\s+/', trim($logisticsBusinessName))
    )
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    $logisticsInitials = $logisticsInitials ?: 'LG';

    /*
    |--------------------------------------------------------------------------
    | ROUTE HELPER
    |--------------------------------------------------------------------------
    |
    | Avoid RouteNotFoundException when a module has not
    | yet been registered.
    |
    | Unavailable links return to the dashboard.
    |
    */

    $logisticsUrl = function ($routeName) {
        if (\Illuminate\Support\Facades\Route::has($routeName)) {
            return route($routeName);
        }

        return \Illuminate\Support\Facades\Route::has(
            'logistics.dashboard'
        )
            ? route('logistics.dashboard')
            : url('/logistics/dashboard');
    };

    $navigationGroups = [
        [
            'label' => 'Overview',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'logistics.dashboard',
                    'icon' => 'dashboard',
                ],
            ],
        ],
        [
            'label' => 'Operations',
            'items' => [
                [
                    'label' => 'Pickup Requests',
                    'route' => 'logistics.pickup.index',
                    'icon' => 'package',
                ],
                [
                    'label' => 'Pickup Scan',
                    'route' => 'logistics.pickup.scan',
                    'icon' => 'package',
                ],
                [
                    'label' => 'Incoming Parcels',
                    'route' => 'logistics.incoming.index',
                    'icon' => 'incoming',
                ],
                [
                    'label' => 'Parcel Sorting',
                    'route' => 'logistics.sorting.index',
                    'icon' => 'sorting',
                ],
                [
                    'label' => 'Delivery Assignment',
                    'route' => 'logistics.assignment.index',
                    'icon' => 'truck',
                ],
                [
                    'label' => 'Delivery Monitoring',
                    'route' => 'logistics.monitoring.index',
                    'icon' => 'clock',
                ],
            ],
        ],
        [
            'label' => 'Communication',
            'items' => [
                [
                    'label' => 'Messages',
                    'route' => 'logistics.messages.index',
                    'icon' => 'message',
                ],
            ],
        ],
        [
            'label' => 'Reports',
            'items' => [
                [
                    'label' => 'Generate Reports',
                    'route' => 'logistics.reports.index',
                    'icon' => 'report',
                ],
            ],
        ],
    ];
@endphp


<div class="erp-layout">

    {{-- =====================================================
         MOBILE SIDEBAR OVERLAY
         ===================================================== --}}

    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
    ></div>


    {{-- =====================================================
         SIDEBAR
         ===================================================== --}}

    <aside
        class="erp-sidebar"
        id="erpSidebar"
    >

        {{-- Brand --}}
        <div class="sidebar-brand">

            <a
                href="{{ $logisticsUrl('logistics.dashboard') }}"
                class="brand-link"
            >

                <img
                    src="{{ asset('images/lumear-logo.png') }}"
                    alt="Lumear Logo"
                    class="brand-logo"
                >

                <div class="brand-copy">
                    <span class="brand-name">Lumear</span>
                    <span class="brand-module">Logistics</span>
                </div>

            </a>

            <button
                type="button"
                class="sidebar-close"
                id="sidebarClose"
                aria-label="Close sidebar"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M18 6 6 18"/>
                    <path d="m6 6 12 12"/>
                </svg>
            </button>

        </div>


        {{-- =================================================
             REGISTERED SORTING CENTER
             ================================================= --}}

        <div class="center-summary">

            <div class="center-avatar">
                {{ $logisticsInitials }}
            </div>

            <div class="center-summary-copy">

                <span class="center-label">
                    SORTING CENTER
                </span>

                <strong>
                    {{ $logisticsBusinessName }}
                </strong>

                <span class="logistics-center-location">
                    {{ $logisticsMunicipality }},
                    {{ $logisticsProvince }}
                </span>

                <span class="center-status">

                    <i></i>

                    {{ $logisticsStatus === 'approved'
                        ? 'Operational'
                        : ucfirst($logisticsStatus) }}

                </span>

            </div>

        </div>


        {{-- =================================================
             SIDEBAR NAVIGATION
             ================================================= --}}

        <nav class="sidebar-navigation">

            @foreach($navigationGroups as $group)

                <div class="navigation-group">

                    <span class="navigation-label">
                        {{ $group['label'] }}
                    </span>

                    @foreach($group['items'] as $item)

                        @php
                            $isActive = request()->routeIs(
                                $item['route']
                            ) || request()->routeIs(
                                $item['route'] . '.*'
                            );

                            $iconName = $item['icon'];
                        @endphp

                        <a
                            href="{{ $logisticsUrl($item['route']) }}"
                            class="navigation-item {{ $isActive ? 'active' : '' }}"
                        >

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                @switch($iconName)

                                    @case('dashboard')
                                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                                        @break

                                    @case('package')
                                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                        <path d="m3.3 7 8.7 5 8.7-5"/>
                                        @break

                                    @case('incoming')
                                        <path d="M4 7h16v13H4z"/>
                                        <path d="M8 7V4h8v3"/>
                                        <path d="M8 12h8"/>
                                        @break

                                    @case('sorting')
                                        <path d="M4 5h16"/>
                                        <path d="M7 10h10"/>
                                        <path d="M10 15h4"/>
                                        <path d="M12 15v5"/>
                                        @break

                                    @case('truck')
                                        <circle cx="7" cy="17" r="2"/>
                                        <circle cx="17" cy="17" r="2"/>
                                        <path d="M5 17H3V6h11v11H9"/>
                                        <path d="M14 10h4l3 3v4h-2"/>
                                        @break

                                    @case('clock')
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="M12 7v5l3 2"/>
                                        @break

                                    @case('message')
                                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
                                        @break

                                    @case('report')
                                        <path d="M4 20V10"/>
                                        <path d="M10 20V4"/>
                                        <path d="M16 20v-7"/>
                                        <path d="M22 20H2"/>
                                        @break

                                @endswitch

                            </svg>

                            <span>
                                {{ $item['label'] }}
                            </span>

                        </a>

                    @endforeach

                </div>

                {{-- Rider Management after Operations --}}
                @if($group['label'] === 'Operations')

                    <div class="navigation-group">

                        <span class="navigation-label">
                            Management
                        </span>

                        <button
                            type="button"
                            class="navigation-item navigation-dropdown-button {{ request()->routeIs('logistics.riders.*') ? 'active' : '' }}"
                            id="riderMenuButton"
                            aria-expanded="{{ request()->routeIs('logistics.riders.*') ? 'true' : 'false' }}"
                            aria-controls="riderSubmenu"
                        >

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M3 21v-2a6 6 0 0 1 12 0v2"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                <path d="M21 21v-2a6 6 0 0 0-4.5-5.8"/>
                            </svg>

                            <span>
                                Rider Management
                            </span>

                            <svg
                                class="dropdown-arrow"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="m9 18 6-6-6-6"/>
                            </svg>

                        </button>

                        <div
                            class="navigation-submenu"
                            id="riderSubmenu"
                        >

                            <a
                                href="{{ $logisticsUrl('logistics.riders.applications') }}"
                                class="{{ request()->routeIs('logistics.riders.applications') ? 'active' : '' }}"
                            >
                                Applications
                            </a>

                            <a
                                href="{{ $logisticsUrl('logistics.riders.index') }}"
                                class="{{ request()->routeIs('logistics.riders.index') ? 'active' : '' }}"
                            >
                                Rider List
                            </a>

                        </div>

                    </div>

                @endif

            @endforeach

        </nav>


        {{-- =================================================
             SIDEBAR BOTTOM
             ================================================= --}}

        <div class="sidebar-bottom">

            <a
                href="{{ $logisticsUrl('logistics.account.index') }}"
                class="navigation-item {{ request()->routeIs('logistics.account.*') ? 'active' : '' }}"
            >

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>

                <span>
                    My Account
                </span>

            </a>


            <form
                action="{{ $logisticsUrl('logistics.logout') }}"
                method="POST"
                class="logistics-logout-form"
            >
                @csrf

                <button
                    type="submit"
                    class="navigation-item logout-item logistics-logout-button"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M10 17l5-5-5-5"/>
                        <path d="M15 12H3"/>
                        <path d="M14 3h7v18h-7"/>
                    </svg>

                    <span>
                        Logout
                    </span>

                </button>

            </form>

        </div>

    </aside>


    {{-- =====================================================
         MAIN AREA
         ===================================================== --}}

    <div class="erp-main">

        {{-- TOPBAR --}}
        <header class="erp-topbar">

            <div class="topbar-left">

                {{-- Mobile menu --}}
                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobileMenuButton"
                    aria-label="Open sidebar"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M4 6h16"/>
                        <path d="M4 12h16"/>
                        <path d="M4 18h16"/>
                    </svg>
                </button>


                {{-- Search UI --}}
                <div class="topbar-search">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>

                    <input
                        type="search"
                        placeholder="Search parcel ID, rider or order..."
                        aria-label="Search logistics"
                    >

                </div>

            </div>


            <div class="topbar-actions">

                {{-- Current date --}}
                <div class="topbar-date">
                    <span id="currentDate">
                        {{ now()->format('D, M j, Y') }}
                    </span>
                </div>


                {{-- Theme toggle --}}
                <button
                    type="button"
                    class="theme-toggle"
                    id="themeToggle"
                    aria-label="Switch theme"
                    title="Switch theme"
                >

                    <svg
                        class="theme-sun"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2"/>
                        <path d="M12 20v2"/>
                        <path d="m4.93 4.93 1.41 1.41"/>
                        <path d="m17.66 17.66 1.41 1.41"/>
                        <path d="M2 12h2"/>
                        <path d="M20 12h2"/>
                        <path d="m6.34 17.66-1.41 1.41"/>
                        <path d="m19.07 4.93-1.41 1.41"/>
                    </svg>

                    <svg
                        class="theme-moon"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
                    </svg>

                </button>


                {{-- =================================================
                     NOTIFICATIONS
                     ================================================= --}}

                <div class="topbar-dropdown-wrapper">

                    <button
                        type="button"
                        class="topbar-icon-button"
                        id="notificationButton"
                        aria-label="Notifications"
                        aria-expanded="false"
                        aria-controls="notificationDropdown"
                    >

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                            <path d="M10 21h4"/>
                        </svg>

                    </button>

                    <div
                        class="topbar-dropdown notification-dropdown"
                        id="notificationDropdown"
                    >

                        <div class="dropdown-heading">

                            <strong>
                                Notifications
                            </strong>

                            <span>
                                No new alerts
                            </span>

                        </div>

                        <div class="logistics-empty-notification">

                            No notifications yet.

                            <p style="margin-top:8px;">
                                Pickup requests, rider applications,
                                and delivery updates will appear
                                once notifications are connected
                                to the database.
                            </p>

                        </div>

                    </div>

                </div>


                {{-- =================================================
                     LOGISTICS PROFILE
                     ================================================= --}}

                <div class="topbar-dropdown-wrapper">

                    <button
                        type="button"
                        class="profile-button"
                        id="profileButton"
                        aria-expanded="false"
                        aria-controls="profileDropdown"
                    >

                        <div class="profile-avatar">
                            {{ $logisticsInitials }}
                        </div>

                        <div class="profile-copy">

                            <strong>
                                {{ $logisticsBusinessName }}
                            </strong>

                            <span>
                                {{ $logisticsOwnerName }}
                            </span>

                        </div>

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="m6 9 6 6 6-6"/>
                        </svg>

                    </button>


                    {{-- Profile dropdown --}}
                    <div
                        class="topbar-dropdown profile-dropdown"
                        id="profileDropdown"
                    >

                        <div class="logistics-profile-owner">

                            <strong>
                                {{ $logisticsBusinessName }}
                            </strong>

                            <div style="margin-top:5px;">
                                {{ $logisticsMunicipality }},
                                {{ $logisticsProvince }}
                            </div>

                            <div style="margin-top:5px;">
                                {{ $logisticsUser->email ?? '' }}
                            </div>

                        </div>

                        <a
                            href="{{ $logisticsUrl('logistics.account.index') }}"
                        >
                            My Account
                        </a>

                        <a
                            href="{{ $logisticsUrl('login') }}"
                        >
                            Marketplace
                        </a>

                        <form
                            action="{{ $logisticsUrl('logistics.logout') }}"
                            method="POST"
                            class="logistics-profile-logout-form"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="profile-logout logistics-profile-logout-button"
                            >
                                Logout
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </header>


        {{-- =================================================
             PAGE CONTENT
             ================================================= --}}

        <main class="erp-content">

            @yield('content')

        </main>

    </div>

</div>


{{-- Existing Logistics JavaScript --}}
<script src="{{ asset('js/logistics/dashboard.js') }}"></script>

{{-- Page-specific JavaScript --}}
@stack('scripts')

</body>
</html>
