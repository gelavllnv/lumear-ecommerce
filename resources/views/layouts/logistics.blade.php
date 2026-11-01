<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Lumear Logistics')
    </title>


    {{-- =====================================================
         APPLY SAVED THEME BEFORE PAGE LOADS

         This prevents the page from flashing light mode
         before JavaScript loads.
         ===================================================== --}}

    <script>

        (function () {

            const savedTheme =
                localStorage.getItem(
                    "lumear_logistics_theme"
                );

            document.documentElement.setAttribute(
                "data-theme",
                savedTheme === "light"
                    ? "light"
                    : "dark"
            );

        })();

    </script>


    {{-- Google Fonts --}}
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    {{-- Logistics ERP CSS --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/logistics/dashboard.css') }}"
    >

</head>


<body>


<div class="erp-layout">


    {{-- =====================================================
         MOBILE OVERLAY
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
                href="{{ route('logistics.dashboard') }}"
                class="brand-link"
            >

                <img
                    src="{{ asset('images/lumear-logo.png') }}"
                    alt="Lumear Logo"
                    class="brand-logo"
                >

                <div class="brand-copy">

                    <span class="brand-name">
                        Lumear
                    </span>

                    <span class="brand-module">
                        Logistics
                    </span>

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
             SORTING CENTER INFORMATION
             ================================================= --}}

        <div class="center-summary">

            <div class="center-avatar">
                SC
            </div>

            <div class="center-summary-copy">

                <span class="center-label">
                    SORTING CENTER
                </span>

                <strong>
                    Santa Cruz Hub
                </strong>

                <span class="center-status">

                    <i></i>

                    Operational

                </span>

            </div>

        </div>



        {{-- =================================================
             NAVIGATION
             ================================================= --}}

        <nav class="sidebar-navigation">


            {{-- Overview --}}
            <div class="navigation-group">

                <span class="navigation-label">
                    Overview
                </span>


                <a
                    href="{{ route('logistics.dashboard') }}"
                    class="navigation-item {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>

                    <span>
                        Dashboard
                    </span>

                </a>

            </div>



            {{-- Operations --}}
            <div class="navigation-group">

                <span class="navigation-label">
                    Operations
                </span>


                <a
                    href="{{ route('logistics.pickup.index') }}"
                    class="navigation-item {{ request()->routeIs('logistics.pickup.*') ? 'active' : '' }}"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                        <path d="m3.3 7 8.7 5 8.7-5"/>
                        <path d="M12 22V12"/>
                    </svg>

                    <span>
                        Pickup Requests
                    </span>

                    <span class="nav-badge">
                        8
                    </span>

                </a>


                <a
                    href="#"
                    class="navigation-item"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M4 7h16v13H4z"/>
                        <path d="M8 7V4h8v3"/>
                        <path d="M8 12h8"/>
                    </svg>

                    <span>
                        Incoming Parcels
                    </span>

                </a>


                <a
                    href="#"
                    class="navigation-item"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M4 5h16"/>
                        <path d="M7 10h10"/>
                        <path d="M10 15h4"/>
                        <path d="M12 15v5"/>
                    </svg>

                    <span>
                        Parcel Sorting
                    </span>

                </a>


                <a
                    href="#"
                    class="navigation-item"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="7" cy="17" r="2"/>
                        <circle cx="17" cy="17" r="2"/>
                        <path d="M5 17H3V6h11v11H9"/>
                        <path d="M14 10h4l3 3v4h-2"/>
                    </svg>

                    <span>
                        Delivery Assignment
                    </span>

                </a>


                <a
                    href="#"
                    class="navigation-item"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>

                    <span>
                        Delivery Monitoring
                    </span>

                </a>

            </div>



            {{-- Management --}}
            <div class="navigation-group">

                <span class="navigation-label">
                    Management
                </span>


                <button
                    type="button"
                    class="navigation-item navigation-dropdown-button"
                    id="riderMenuButton"
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

                    <a href="#">

                        Applications

                        <span>
                            3
                        </span>

                    </a>


                    <a href="#">
                        Rider List
                    </a>

                </div>

            </div>



            {{-- Communication --}}
            <div class="navigation-group">

                <span class="navigation-label">
                    Communication
                </span>


                <a
                    href="#"
                    class="navigation-item"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
                    </svg>

                    <span>
                        Messages
                    </span>

                    <span class="notification-dot"></span>

                </a>

            </div>



            {{-- Reports --}}
            <div class="navigation-group">

                <span class="navigation-label">
                    Reports
                </span>


                <a
                    href="#"
                    class="navigation-item"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M4 20V10"/>
                        <path d="M10 20V4"/>
                        <path d="M16 20v-7"/>
                        <path d="M22 20H2"/>
                    </svg>

                    <span>
                        Generate Reports
                    </span>

                </a>

            </div>

        </nav>



        {{-- =================================================
             SIDEBAR BOTTOM
             ================================================= --}}

        <div class="sidebar-bottom">

            <a
                href="#"
                class="navigation-item"
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


            <a
                href="{{ route('logistics.login') }}"
                class="navigation-item logout-item"
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

            </a>

        </div>


    </aside>



    {{-- =====================================================
         MAIN AREA
         ===================================================== --}}

    <div class="erp-main">


        {{-- =================================================
             TOPBAR
             ================================================= --}}

        <header class="erp-topbar">


            <div class="topbar-left">

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
                    >

                </div>

            </div>



            <div class="topbar-actions">


                {{-- Current Date --}}
                <div class="topbar-date">

                    <span id="currentDate">
                        Loading date...
                    </span>

                </div>



                {{-- =================================================
                     THEME TOGGLE
                     ================================================= --}}

                <button
                    type="button"
                    class="theme-toggle"
                    id="themeToggle"
                    aria-label="Switch to light mode"
                    title="Switch theme"
                >

                    {{-- Sun --}}
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


                    {{-- Moon --}}
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

                        <span class="topbar-alert"></span>

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
                                3 new
                            </span>

                        </div>


                        <div class="notification-entry unread">

                            <span class="notification-symbol">
                                P
                            </span>

                            <div>

                                <strong>
                                    New pickup request
                                </strong>

                                <p>
                                    Seller requested pickup for 4 parcels.
                                </p>

                                <small>
                                    8 minutes ago
                                </small>

                            </div>

                        </div>


                        <div class="notification-entry unread">

                            <span class="notification-symbol">
                                R
                            </span>

                            <div>

                                <strong>
                                    Rider application
                                </strong>

                                <p>
                                    A new rider application needs review.
                                </p>

                                <small>
                                    24 minutes ago
                                </small>

                            </div>

                        </div>


                        <div class="notification-entry">

                            <span class="notification-symbol">
                                D
                            </span>

                            <div>

                                <strong>
                                    Delivery completed
                                </strong>

                                <p>
                                    Parcel LMR-2418 has been delivered.
                                </p>

                                <small>
                                    1 hour ago
                                </small>

                            </div>

                        </div>

                    </div>

                </div>



                {{-- =================================================
                     PROFILE
                     ================================================= --}}

                <div class="topbar-dropdown-wrapper">

                    <button
                        type="button"
                        class="profile-button"
                        id="profileButton"
                    >

                        <div class="profile-avatar">
                            SC
                        </div>

                        <div class="profile-copy">

                            <strong>
                                Santa Cruz Hub
                            </strong>

                            <span>
                                Logistics Operator
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


                    <div
                        class="topbar-dropdown profile-dropdown"
                        id="profileDropdown"
                    >

                        <a href="#">
                            My Account
                        </a>

                        <a href="{{ route('login') }}">
                            Marketplace
                        </a>

                        <a
                            href="{{ route('logistics.login') }}"
                            class="profile-logout"
                        >
                            Logout
                        </a>

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


{{-- Logistics ERP JavaScript --}}
<script src="{{ asset('js/logistics/dashboard.js') }}"></script>


</body>

</html>