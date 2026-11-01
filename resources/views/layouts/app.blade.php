
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Lumear — Shop the good stuff')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: {
                            50: '#FBF5F1',
                            100: '#F3E4DA',
                            200: '#E0CFC2',
                            300: '#CAAE9F'
                        },
                        sand: {
                            400: '#CAAE9F',
                            500: '#9E7161'
                        },
                        maroon: {
                            950: '#3D0F11',
                            900: '#62191C',
                            800: '#62191C',
                            700: '#873632',
                            600: '#873632',
                            500: '#9E7161',
                            400: '#9E7161',
                            300: '#B99184'
                        },
                        gold: {
                            500: '#9E7161',
                            400: '#CAAE9F',
                            300: '#E0CFC2'
                        }
                    },
                    fontFamily: {
                        display: ['"Fraunces"', 'serif'],
                        body: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    boxShadow: {
                        soft: '0 12px 32px -12px rgba(46,10,19,.18)',
                        lift: '0 20px 40px -16px rgba(46,10,19,.28)'
                    },
                    keyframes: {
                        rise: {
                            '0%': {
                                opacity: 0,
                                transform: 'translateY(14px)'
                            },
                            '100%': {
                                opacity: 1,
                                transform: 'translateY(0)'
                            }
                        },
                        marquee: {
                            '0%': { transform: 'translateX(0)' },
                            '100%': { transform: 'translateX(-50%)' }
                        }
                    },
                    animation: {
                        rise: 'rise .6s cubic-bezier(.16,1,.3,1) both',
                        marquee: 'marquee 28s linear infinite'
                    }
                }
            }
        };
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        html {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .font-display {
            font-family: 'Fraunces', serif;
        }

        [x-cloak] {
            display: none !important;
        }

        .link-grow {
            position: relative;
        }

        .link-grow::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 0;
            height: 1.5px;
            background: currentColor;
            transition: width .35s cubic-bezier(.16,1,.3,1);
        }

        .link-grow:hover::after {
            width: 100%;
        }

        :focus-visible {
            outline: 2px solid #873632;
            outline-offset: 2px;
        }
    </style>

    {{-- Shared navbar and footer styling --}}
    <link rel="stylesheet"
          href="{{ asset('css/buyer/storefront.css') }}?v=11">

    {{-- Keep the existing Lumear interactions (cart, checkout, address picker) available. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Individual Buyer page styles --}}
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/buyer/dark-mode.css') }}?v=8">
</head>

<body class="lm-buyer-dark bg-cream-100 text-maroon-900 antialiased">

    @include('partials.navbar')

    <main id="buyerMainContent">
        @if(session('status'))
            <div class="lm-shell pt-4">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="lm-shell pt-4">
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ $errors->first() }}
                </div>
            </div>
        @endif

        @if(request()->routeIs('shop'))
            {{ $slot ?? '' }}
            @yield('content')
        @else
            <div class="mx-auto max-w-7xl px-5 py-8">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        @endif
    </main>

    @include('partials.footer')

    <script src="{{ asset('js/buyer/storefront.js') }}?v=11" defer></script>

    @stack('scripts')

    @if(request()->is('home') || request()->is('/'))
    <script src="{{ asset('js/buyer/home-product-navigation.js') }}?v=2" defer></script>
    @endif
</body>
</html>
