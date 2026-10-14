<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Lumear')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell min-h-screen bg-cream-100 text-maroon-900">
    <header class="app-header sticky top-0 z-50 bg-cream-50 shadow-[0_1px_0_rgba(98,25,28,.1)]">
        <div class="app-announcement bg-maroon-950 px-5 py-2 text-center text-[11px] font-bold tracking-[.12em] text-cream-100">LOCAL FINDS · COD CHECKOUT · THOUGHTFUL SHOPPING</div>
        <div class="mx-auto flex h-[78px] max-w-7xl items-center gap-6 px-5">
            <a href="{{ route('shop') }}" class="flex shrink-0 items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-xl bg-maroon-900 font-display text-xl text-cream-50">L</span><span class="font-display text-3xl italic tracking-tight">Lumear</span></a>
            <form action="{{ route('shop') }}" class="app-search hidden h-11 max-w-2xl flex-1 overflow-hidden rounded-xl border border-sand-400/60 bg-cream-50 transition focus-within:border-maroon-700 md:flex"><input class="min-w-0 flex-1 bg-transparent px-5 text-sm outline-none" name="q" value="{{ request('q') }}" placeholder="Search products, categories, or orders"><button class="w-12 text-lg text-maroon-700" aria-label="Search">⌕</button></form>
            <div class="ml-auto flex items-center gap-4 text-sm font-semibold">
                @auth
                    @if(auth()->user()->isBuyer())
                        <a class="relative nav-link" href="{{ route('cart') }}">Bag <span class="absolute -right-4 -top-3 grid h-5 min-w-5 place-items-center rounded-full bg-maroon-700 px-1 text-[10px] text-cream-50">{{ auth()->user()->cart?->items()->sum('quantity') ?? 0 }}</span></a><a class="nav-link hidden sm:block" href="{{ route('orders') }}">My orders</a>
                    @elseif(auth()->user()->isAdmin())
                        <a class="nav-link hidden sm:block" href="{{ route('admin.dashboard') }}">Admin Centre</a>
                    @elseif(auth()->user()->isSeller())
                        <a class="nav-link hidden sm:block" href="{{ route('seller.status') }}">Seller Centre</a>
                    @endif
                    <a class="flex items-center gap-2 rounded-full border border-sand-400/50 px-2 py-1.5 transition hover:border-maroon-700 hover:bg-cream-100" href="{{ route('account.show') }}" aria-label="Open your profile"><span class="grid h-6 w-6 place-items-center rounded-full bg-maroon-700 text-[11px] text-cream-50">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="hidden max-w-24 truncate sm:block">Profile</span></a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="nav-link">Log out</button></form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Sign in</a><a class="rounded-full bg-maroon-700 px-4 py-2 text-cream-50 transition hover:bg-maroon-900" href="{{ route('register') }}">Join Lumear</a>
                @endauth
            </div>
        </div>
        <div class="border-y border-sand-400/35"><nav class="mx-auto flex h-11 max-w-7xl items-center gap-6 overflow-x-auto whitespace-nowrap px-5 text-[12px] font-bold uppercase tracking-[.08em]"><a class="nav-link text-maroon-700" href="{{ route('shop') }}">All finds</a>@foreach(['Fashion','Beauty & Wellness','Home & Living','Electronics','Pet Supplies','Sports & Outdoor','Books & Stationery','Food & Drinks'] as $category)<a class="nav-link" href="{{ route('shop',['q' => $category]) }}">{{ $category }}</a>@endforeach</nav></div>
    </header>
    <main class="mx-auto max-w-7xl px-5 py-8">
        @if(session('status'))<div class="rise mb-6 flex gap-3 rounded-xl border border-emerald-700/15 bg-emerald-50 p-4 text-emerald-900"><span>✓</span><span>{{ session('status') }}</span></div>@endif
        @if($errors->any())<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="mt-14 bg-maroon-950 text-cream-200"><div class="mx-auto grid max-w-7xl gap-5 px-5 py-10 sm:grid-cols-2"><div><span class="font-display text-3xl italic text-cream-50">Lumear</span><p class="mt-3 text-sm text-cream-200/70">Curated everyday finds from independent shops.</p></div><p class="self-end text-xs text-cream-200/60 sm:text-right">© {{ date('Y') }} Lumear · Made for beautiful everyday moments.</p></div></footer>
</body>
</html>
