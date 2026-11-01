<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Lumear Operations')</title>
    <script>
        (() => {
            const savedTheme = localStorage.getItem('lumear_logistics_theme');

            if (savedTheme === 'light' || savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/logistics/dashboard.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/seller/operations-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/seller/light-mode-overrides.css') }}">
    @stack('styles')
</head>
<body class="app-shell">
@php($admin = auth()->user()?->isAdmin())
<div class="erp-layout">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="erp-sidebar" id="erpSidebar">
        <div class="sidebar-brand">
            <a href="{{ $admin ? route('admin.dashboard') : route('seller.dashboard') }}" class="brand-link">
                <img src="{{ asset('images/lumear-logo.png') }}" alt="Lumear" class="brand-logo">
                <div class="brand-copy"><span class="brand-name">Lumear</span><span class="brand-module">{{ $admin ? 'Administration' : 'Seller Centre' }}</span></div>
            </a>
            <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close navigation">×</button>
        </div>
        <div class="center-summary"><div class="center-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div><div class="center-summary-copy"><span class="center-label">{{ $admin ? 'CONTROL CENTRE' : 'YOUR SHOP' }}</span><strong>{{ $admin ? 'Lumear Admin' : (auth()->user()->seller?->name ?? 'Seller Centre') }}</strong><span class="center-status"><i></i>Operational</span></div></div>
        <nav class="sidebar-navigation">
            <div class="navigation-group"><span class="navigation-label">Overview</span><a href="{{ $admin ? route('admin.dashboard') : route('seller.dashboard') }}" class="navigation-item {{ request()->routeIs($admin ? 'admin.dashboard' : 'seller.dashboard') ? 'active' : '' }}"><span>▦</span><span>Dashboard</span></a></div>
            @if($admin)
                <div class="navigation-group"><span class="navigation-label">Marketplace control</span><a href="{{ route('admin.dashboard') }}#seller-approvals" class="navigation-item"><span>◈</span><span>Seller approvals</span></a><a href="{{ route('admin.dashboard') }}#kyc-review" class="navigation-item"><span>▣</span><span>KYC review</span></a><a href="{{ route('admin.dashboard') }}#finance" class="navigation-item"><span>₱</span><span>Manual finance</span></a><a href="{{ route('admin.dashboard') }}#moderation" class="navigation-item"><span>◉</span><span>Moderation</span></a></div>
            @else
                @php($sellerNavigation = ['products.index' => ['Products', '▦'], 'store-categories.index' => ['Store categories', '▤'], 'inventory' => ['Inventory', '▤'], 'orders' => ['Order management', '◷'], 'returns.index' => ['Returns & refunds', '↶'], 'promotions.index' => ['Promotions', '✦'], 'messages' => ['Messages', '✉'], 'analytics' => ['Analytics', '⌁'], 'earnings' => ['Earnings', '₱'], 'settings' => ['My shop', '⚙']])
                <div class="navigation-group seller-navigation-group"><span class="navigation-label">Seller operations</span>@foreach($sellerNavigation as $route => [$label, $icon])<a href="{{ route('seller.'.$route) }}" class="navigation-item seller-navigation-item {{ request()->routeIs('seller.'.$route) ? 'active' : '' }}"><span class="navigation-icon" aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span></a>@endforeach</div>
            @endif
        </nav>
        <div class="sidebar-bottom"><a href="{{ route('account.show') }}" class="navigation-item seller-profile-link {{ request()->routeIs('account.show') ? 'active' : '' }}"><span class="seller-profile-mini">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span><b>My profile</b><small>Account settings</small></span></a><form method="post" action="{{ route('logout') }}">@csrf<button class="navigation-item logout-item w-full text-left" type="submit"><span class="navigation-icon" aria-hidden="true">↪</span><span>Log out</span></button></form></div>
    </aside>
    <div class="erp-main">
        <header class="erp-topbar"><div class="topbar-left"><button type="button" class="mobile-menu-button" id="mobileMenuButton" aria-label="Open navigation">☰</button><div class="topbar-search"><span>⌕</span><input type="search" placeholder="Search orders, products, or customers…" aria-label="Search"></div></div><div class="topbar-actions"><div class="topbar-date"><span id="currentDate">{{ now()->format('D, M j, Y') }}</span></div><button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch theme">◐</button><a class="profile-button" href="{{ route('account.show') }}"><div class="profile-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div><div class="profile-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ $admin ? 'Administrator' : 'Seller' }}</span></div></a></div></header>
        <main class="erp-content">
            @if(session('status'))<div class="mb-6 rounded-xl border border-emerald-700/25 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="mb-6 rounded-xl border border-red-400/40 bg-red-50 p-4 text-sm text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('operations-content')
        </main>
    </div>
</div>
<script src="{{ asset('js/logistics/dashboard.js') }}"></script>
@stack('scripts')
</body>
</html>
