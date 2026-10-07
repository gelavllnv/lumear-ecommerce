<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Lumear')
    </title>


    {{-- =====================================================
         GOOGLE FONTS
         ===================================================== --}}

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


    {{-- =====================================================
         BUYER AUTH CSS

         File:
         public/css/buyer/auth.css
         ===================================================== --}}

    <link
        rel="stylesheet"
        href="{{ asset('css/buyer/auth.css') }}"
    >

</head>


<body>


<div class="auth-page">


    {{-- =====================================================
         BACKGROUND DECORATIONS
         ===================================================== --}}

    <div
        class="background-decoration decoration-one"
    ></div>

    <div
        class="background-decoration decoration-two"
    ></div>



    {{-- =====================================================
         NAVIGATION
         ===================================================== --}}

    <header class="auth-nav">


        {{-- =================================================
             LUMEAR BRAND
             ================================================= --}}

        <a
            href="{{ url('/') }}"
            class="auth-brand"
        >

            <img
                src="{{ asset('images/lumear-logo.png') }}"
                alt="Lumear Logo"
                class="auth-brand-logo"
            >


            <span class="auth-brand-name">
                Lumear
            </span>


            <span class="auth-page-name">
                @yield('pageTitle', 'Account')
            </span>

        </a>



        {{-- =================================================
             LOGISTICS PARTNER PORTAL

             Clicking this now opens:
             /logistics/login
             ================================================= --}}

        <a
            href="{{ route('logistics.login') }}"
            class="logistics-portal"
            title="Open Logistics Portal"
        >


            {{-- Logistics icon --}}
            <div class="logistics-portal-icon">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <path d="M3 6h11v11H3z" />

                    <path d="M14 10h4l3 3v4h-7z" />

                    <circle
                        cx="7"
                        cy="18"
                        r="2"
                    />

                    <circle
                        cx="18"
                        cy="18"
                        r="2"
                    />

                </svg>

            </div>



            {{-- Logistics text --}}
            <div class="logistics-portal-text">

                <span class="logistics-portal-label">
                    Partner Portal
                </span>

                <span class="logistics-portal-title">
                    Logistics
                </span>

            </div>



            {{-- Arrow --}}
            <svg
                class="logistics-portal-arrow"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <path d="M5 12h14" />

                <path d="m13 6 6 6-6 6" />

            </svg>


        </a>


    </header>



    {{-- =====================================================
         PAGE CONTENT

         Examples:
         buyer/auth/login.blade.php
         buyer/auth/register.blade.php
         ===================================================== --}}

    @yield('content')


</div>



{{-- =========================================================
     BUYER AUTH JAVASCRIPT

     File:
     public/js/buyer/auth.js
     ========================================================= --}}

<script src="{{ asset('js/buyer/auth.js') }}"></script>


</body>

</html>