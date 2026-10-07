<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Lumear Logistics')
    </title>


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


    {{-- Logistics Authentication CSS --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/logistics/auth.css') }}"
    >

</head>


<body>

<div class="logistics-auth-page">


    {{-- Decorative background --}}
    <div class="background-orb orb-one"></div>
    <div class="background-orb orb-two"></div>



    {{-- =====================================================
         TOP NAVIGATION
         ===================================================== --}}

    <header class="logistics-auth-nav">


        {{-- Logo / Brand --}}
        <a
            href="{{ route('logistics.login') }}"
            class="logistics-brand"
        >

            <img
                src="{{ asset('images/lumear-logo.png') }}"
                alt="Lumear Logo"
                class="logistics-brand-logo"
            >

            <div class="logistics-brand-text">

                <span class="logistics-brand-name">
                    Lumear
                </span>

                <span class="logistics-brand-module">
                    Logistics
                </span>

            </div>

        </a>



        {{-- Return to marketplace --}}
        <a
            href="{{ route('login') }}"
            class="marketplace-link"
        >

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="m15 18-6-6 6-6"/>
            </svg>

            <span>
                Return to Marketplace
            </span>

        </a>

    </header>



    {{-- Page --}}
    <main class="logistics-auth-main">

        @yield('content')

    </main>


</div>


{{-- Logistics Authentication JS --}}
<script src="{{ asset('js/logistics/auth.js') }}"></script>


</body>
</html>