
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Lumear')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Exact "Maroon + Beige" palette: 62191C · 873632 · 9E7161 · CAAE9F · E0CFC2
                        cream:  { 50:'#FBF5F1', 100:'#F3E4DA', 200:'#E0CFC2', 300:'#CAAE9F' },
                        sand:   { 400:'#CAAE9F', 500:'#9E7161' },
                        maroon: { 950:'#3D0F11', 900:'#62191C', 800:'#62191C', 700:'#873632', 600:'#873632', 500:'#9E7161', 400:'#9E7161' },
                        gold:   { 500:'#9E7161', 400:'#CAAE9F' },
                    },
                    fontFamily: {
                        display: ['"Fraunces"', 'serif'],
                        body: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    boxShadow: { soft: '0 12px 32px -12px rgba(46,10,19,.18)', lift: '0 24px 60px -16px rgba(15,3,6,.45)' },
                },
            },
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        html { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-display { font-family: 'Fraunces', serif; }
        ::-webkit-scrollbar { display: none; }
        * { scrollbar-width: none; -ms-overflow-style: none; }
        [x-cloak] { display: none !important; }

        /* Keep Chrome/Safari autofill on-palette instead of their default blue tint */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #62191C;
            -webkit-box-shadow: 0 0 0px 1000px #F3E4DA inset;
            box-shadow: 0 0 0px 1000px #F3E4DA inset;
            transition: background-color 9999s ease-in-out 0s;
        }
        .link-grow { position: relative; }
        .link-grow::after { content:''; position:absolute; left:0; bottom:-2px; width:0; height:1.5px; background:currentColor; transition:width .3s cubic-bezier(.16,1,.3,1); }
        .link-grow:hover::after { width:100%; }

        /* Texture: faint dot grid over the maroon field, Shopee-esque without copying its orange */
        .dot-field {
            background-image: radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 22px 22px;
        }
    </style>
</head>
<body class="bg-cream-50 antialiased">

<div class="min-h-screen flex flex-col">

    {{-- Plain top strip — logo + page title on the left, Need help? on the right --}}
    <div class="h-16 shrink-0 bg-cream-50 border-b border-sand-400/40 flex items-center justify-between px-6 md:px-10">
        <div class="flex items-center gap-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/lumear-logo.png') }}" alt="Lumear" class="w-8 h-8 rounded-full object-contain">
                <span class="font-display italic text-lg text-maroon-800">Lumear</span>
            </a>
            <span class="text-sand-400 text-lg font-light">|</span>
            <span class="text-maroon-900/70 text-sm">@yield('pageTitle', 'Log In')</span>
        </div>
        <a href="#" class="text-sm text-maroon-700 font-medium link-grow">Need help?</a>
    </div>

    {{-- Banner stage — full-bleed maroon field, carousel + card float asymmetrically like Shopee's promo/login split --}}
    <div class="flex-1 relative overflow-hidden dot-field bg-gradient-to-br from-maroon-800 via-maroon-900 to-maroon-950">

        <div class="absolute -top-32 -left-32 w-[30rem] h-[30rem] rounded-full bg-maroon-700/40 blur-3xl"></div>
        <div class="absolute -bottom-40 -right-20 w-[26rem] h-[26rem] rounded-full bg-gold-500/10 blur-3xl"></div>

        <div class="relative z-10 h-full flex items-center justify-center px-6 md:px-12 py-10">
            <div class="w-full max-w-6xl flex items-center justify-between gap-10">
                @include('partials.auth-carousel')

                <div class="w-full max-w-[420px] shrink-0 ml-auto">
                    <div class="bg-cream-50 rounded-lg shadow-lift p-8 md:p-9">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>