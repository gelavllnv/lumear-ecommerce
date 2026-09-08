<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Lumear — Shop the good stuff')</title>

    {{-- ============================================================
         TAILWIND — CDN build, no Vite/npm required.
         Swap this <script> for your compiled tailwind.css whenever
         you do install a build step; nothing else here needs to change.
    ============================================================ --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Exact "Maroon + Beige" palette: 62191C · 873632 · 9E7161 · CAAE9F · E0CFC2
                        cream:  { 50:'#FBF5F1', 100:'#F3E4DA', 200:'#E0CFC2', 300:'#CAAE9F' },
                        sand:   { 400:'#CAAE9F', 500:'#9E7161' },
                        maroon: {
                            950:'#3D0F11', 900:'#62191C', 800:'#62191C',
                            700:'#873632', 600:'#873632', 500:'#9E7161',
                            400:'#9E7161', 300:'#B99184',
                        },
                        gold:   { 500:'#9E7161', 400:'#CAAE9F', 300:'#E0CFC2' },
                    },
                    fontFamily: {
                        display: ['"Fraunces"', 'serif'],
                        body: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    boxShadow: {
                        soft: '0 12px 32px -12px rgba(46, 10, 19, 0.18)',
                        lift: '0 20px 40px -16px rgba(46, 10, 19, 0.28)',
                    },
                    keyframes: {
                        rise:    { '0%': { opacity: 0, transform: 'translateY(14px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } },
                        marquee: { '0%': { transform: 'translateX(0)' }, '100%': { transform: 'translateX(-50%)' } },
                    },
                    animation: {
                        rise: 'rise .6s cubic-bezier(.16,1,.3,1) both',
                        marquee: 'marquee 28s linear infinite',
                    },
                },
            },
        }
    </script>

    {{-- Fonts: an opinionated serif for personality + a geometric sans for UI --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Alpine.js — powers dropdowns, tabs, steppers, no build step needed --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        html { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-display { font-family: 'Fraunces', serif; }
        ::-webkit-scrollbar { display: none; }
        * { scrollbar-width: none; -ms-overflow-style: none; }
        [x-cloak] { display: none !important; }

        /* Underline-grow link, used sparingly for the one "signature" interaction */
        .link-grow { position: relative; }
        .link-grow::after {
            content: ''; position: absolute; left: 0; bottom: -2px; width: 0; height: 1.5px;
            background: currentColor; transition: width .35s cubic-bezier(.16,1,.3,1);
        }
        .link-grow:hover::after { width: 100%; }

        /* Soft focus ring in-brand, keeps keyboard accessibility visible */
        :focus-visible { outline: 2px solid #8B2635; outline-offset: 2px; }
    </style>

    @stack('styles')
</head>
<body class="bg-cream-100 text-maroon-900 antialiased">

    @include('partials.navbar')

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
</html>