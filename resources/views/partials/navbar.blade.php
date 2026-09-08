<header x-data="{ mega: false, account: false, notif: false, lang: false, currentLang: 'English', identity: localStorage.getItem('lumear_identity') || 'Guest' }" class="sticky top-0 z-50">

    {{-- Utility bar — Seller Centre / Start Selling / Download / Follow us (left),
         Notifications / Help / Language / Account (right), all functional dropdowns --}}
    <div class="bg-maroon-950 text-cream-200/70 text-xs">
        <div class="max-w-7xl mx-auto px-6 flex items-center justify-between h-9">

            <div class="flex items-center gap-5">
                <a href="#" class="link-grow hover:text-cream-100">Seller Centre</a>
                <a href="#" class="link-grow hover:text-cream-100">Start Selling</a>
                <a href="#" class="link-grow hover:text-cream-100">Download</a>
                <div class="flex items-center gap-2">
                    <span>Follow us on</span>
                    <a href="#" class="w-5 h-5 rounded-full bg-cream-100/10 grid place-items-center hover:bg-cream-100/20 transition-colors" title="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12a10 10 0 10-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0022 12z"/></svg>
                    </a>
                    <a href="#" class="w-5 h-5 rounded-full bg-cream-100/10 grid place-items-center hover:bg-cream-100/20 transition-colors" title="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2 0 1.9.2 2.3.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1.1.4 2.3.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c0 1.2-.2 1.9-.4 2.3-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1.1.4-2.3.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2 0-1.9-.2-2.3-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1.1-.4-2.3-.1-1.3-.1-1.7-.1-4.9s0-3.6.1-4.9c0-1.2.2-1.9.4-2.3.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1.1-.4 2.3-.4 1.3-.1 1.7-.1 4.9-.1M12 0C8.7 0 8.3 0 7 .1c-1.3.1-2.2.2-3 .5-.8.3-1.5.7-2.2 1.4C1.1 2.7.7 3.4.4 4.2c-.3.8-.5 1.7-.5 3C0 8.3 0 8.7 0 12s0 3.7.1 5c.1 1.3.2 2.2.5 3 .3.8.7 1.5 1.4 2.2.7.7 1.4 1.1 2.2 1.4.8.3 1.7.5 3 .5C8.3 24 8.7 24 12 24s3.7 0 5-.1c1.3-.1 2.2-.2 3-.5.8-.3 1.5-.7 2.2-1.4.7-.7 1.1-1.4 1.4-2.2.3-.8.5-1.7.5-3 .1-1.3.1-1.7.1-5s0-3.7-.1-5c-.1-1.3-.2-2.2-.5-3-.3-.8-.7-1.5-1.4-2.2C21.3 1.1 20.6.7 19.8.4c-.8-.3-1.7-.5-3-.5C15.7 0 15.3 0 12 0zm0 5.8A6.2 6.2 0 1012 18.2 6.2 6.2 0 0012 5.8zm0 10.2a4 4 0 110-8 4 4 0 010 8zm6.4-10.4a1.4 1.4 0 11-2.9 0 1.4 1.4 0 012.9 0z"/></svg>
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-5">

                {{-- Notifications --}}
                <div class="relative">
                    <button @click="notif = !notif; account = false; lang = false" class="flex items-center gap-1.5 hover:text-cream-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Notifications
                    </button>
                    <div x-cloak x-show="notif" @click.outside="notif = false"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 mt-3 w-80 bg-cream-50 rounded-lg shadow-lift border border-sand-400/40 overflow-hidden text-left">
                        <p class="px-4 py-3 text-[11px] font-semibold text-maroon-900/50 uppercase tracking-wide border-b border-sand-400/30">Recently Received</p>
                        <div class="max-h-72 overflow-y-auto divide-y divide-sand-400/20">
                            @foreach ([
                                ['icon' => '🚚', 'title' => 'Your order has shipped', 'body' => 'Handwoven Rattan Pet Bed is on its way — arriving in 2–3 days.'],
                                ['icon' => '⚡', 'title' => 'Flash Deals start in 1 hour', 'body' => 'Up to 40% off Home & Garden — set a reminder.'],
                                ['icon' => '🎟️', 'title' => 'You have a ₱100 voucher', 'body' => 'Use it on any order above ₱500 before it expires.'],
                            ] as $n)
                                <a href="#" class="flex items-start gap-3 px-4 py-3 hover:bg-sand-400/15 transition-colors">
                                    <span class="text-lg shrink-0">{{ $n['icon'] }}</span>
                                    <span>
                                        <span class="block text-xs font-semibold text-maroon-900">{{ $n['title'] }}</span>
                                        <span class="block text-[11px] text-maroon-900/50 mt-0.5 leading-relaxed">{{ $n['body'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                        <a href="#" class="block text-center text-xs font-semibold text-maroon-700 py-2.5 border-t border-sand-400/30 hover:bg-sand-400/15 transition-colors">View All</a>
                    </div>
                </div>

                <a href="#" class="hover:text-cream-100 transition-colors">Help</a>

                {{-- Language switcher --}}
                <div class="relative">
                    <button @click="lang = !lang; account = false; notif = false" class="flex items-center gap-1.5 hover:text-cream-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 014 9 15 15 0 01-4 9 15 15 0 01-4-9 15 15 0 014-9z"/>
                        </svg>
                        <span x-text="currentLang"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 transition-transform" :class="lang && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-cloak x-show="lang" @click.outside="lang = false"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 mt-3 w-40 bg-cream-50 rounded-lg shadow-lift border border-sand-400/40 py-1.5 overflow-hidden text-left">
                        @foreach (['English', '简体中文', 'Filipino'] as $option)
                            <button @click="currentLang = '{{ $option }}'; lang = false"
                                    class="w-full text-left px-4 py-2 text-sm transition-colors"
                                    :class="currentLang === '{{ $option }}' ? 'text-maroon-700 font-bold bg-sand-400/15' : 'text-maroon-900/70 hover:bg-sand-400/15'">
                                {{ $option }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Account --}}
                <div class="relative">
                    <button @click="account = !account; notif = false; lang = false" class="flex items-center gap-2 hover:text-cream-100 transition-colors">
                        <span class="w-5 h-5 rounded-full bg-cream-100/20 grid place-items-center text-[10px] font-bold overflow-hidden">🧑</span>
                        <span x-text="identity"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 transition-transform" :class="account && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-cloak x-show="account" @click.outside="account = false"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 mt-3 w-48 bg-cream-50 rounded-lg shadow-lift border border-sand-400/40 py-2 overflow-hidden text-left">
                        <a href="{{ url('/account') }}" class="block px-4 py-2.5 text-sm text-maroon-900 hover:bg-sand-400/20">My Account</a>
                        <a href="{{ url('/orders') }}" class="block px-4 py-2.5 text-sm text-maroon-900 hover:bg-sand-400/20">My Purchase</a>
                        <hr class="border-sand-400/40 my-1">
                        <a href="{{ url('/') }}" @click="localStorage.removeItem('lumear_identity')" class="block px-4 py-2.5 text-sm text-maroon-700 font-semibold hover:bg-sand-400/20">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main bar — logo, search (with trending tags), cart --}}
    <div class="bg-maroon-700">
        <div class="max-w-7xl mx-auto px-4 md:px-6 h-[72px] flex items-center gap-6 md:gap-10">

            <a href="{{ url('/home') }}" class="shrink-0 flex items-center gap-2">
                <img src="{{ asset('images/lumear-logo.png') }}" alt="Lumear" class="w-9 h-9 rounded-md object-contain">
                <span class="font-display italic text-2xl tracking-tight text-cream-50 hidden sm:inline">Lumear</span>
            </a>

            <div class="flex-1 max-w-2xl">
                <form action="{{ url('/search') }}" method="GET" class="flex h-11 rounded-md bg-cream-50 overflow-hidden shadow-sm">
                    <input
                        type="text" name="q" placeholder="Search for anything — from cat food to circuit boards"
                        class="flex-1 min-w-0 px-4 text-sm text-maroon-900 placeholder:text-maroon-900/40 outline-none"
                    >
                    <button type="submit" class="w-12 shrink-0 bg-maroon-800 text-cream-50 grid place-items-center hover:bg-maroon-950 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/>
                        </svg>
                    </button>
                </form>
                <div class="hidden md:flex gap-3 mt-1.5 text-[11px] text-cream-100/60">
                    @foreach (['Flash Deals', 'Rattan Home', 'Wireless Earbuds', 'Gift Sets'] as $tag)
                        <a href="#" class="hover:text-cream-50 link-grow">{{ $tag }}</a>
                    @endforeach
                </div>
            </div>

            <a href="{{ url('/cart') }}" class="group relative shrink-0 flex items-center gap-2 text-cream-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 group-hover:-translate-y-0.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l3.6-7.4H5.4M7 13L5.4 5.6M7 13l-2.2 4.4A1 1 0 005.7 19H18M9 21a1 1 0 100-2 1 1 0 000 2zm9 0a1 1 0 100-2 1 1 0 000 2z"/>
                </svg>
                <span class="absolute -top-2 -right-2.5 w-5 h-5 min-w-[20px] px-1 rounded-full bg-cream-50 text-maroon-800 text-[10px] font-bold grid place-items-center">3</span>
            </a>
        </div>
    </div>

    {{-- Category strip with mega-menu, unique to Lumear --}}
    <div class="bg-cream-100 border-b border-sand-400/50">
        <div class="max-w-7xl mx-auto px-4 md:px-6 relative">
            <button @click="mega = !mega" class="flex items-center gap-2 py-2.5 text-sm font-bold text-maroon-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                ALL CATEGORIES
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 transition-transform duration-200" :class="mega && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-cloak x-show="mega" @click.outside="mega = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute left-0 right-0 top-full bg-cream-50 border border-sand-400/40 rounded-b-lg shadow-lift z-40 p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-8 gap-y-4">
                    @foreach ($categories ?? \App\Support\BuyerCategories::all() as $cat)
                        <a href="{{ url('/category/'.$cat['slug']) }}" class="flex items-center gap-3 group py-1.5">
                            <span class="w-9 h-9 rounded-md bg-sand-400/25 grid place-items-center text-lg group-hover:bg-maroon-700 group-hover:text-cream-50 transition-colors duration-200">{{ $cat['icon'] }}</span>
                            <span class="text-sm text-maroon-800 group-hover:text-maroon-600 link-grow">{{ $cat['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Floating chat bubble — Shopee keeps live chat out of the header and docked bottom-right instead --}}
<a href="{{ url('/chat') }}"
   class="fixed bottom-6 right-6 z-40 flex items-center gap-2 bg-maroon-700 text-cream-50 pl-4 pr-5 h-12 rounded-full shadow-lift hover:bg-maroon-600 transition-colors duration-200">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8-1.06 0-2.077-.16-3.02-.457L3 21l1.457-4.98A7.94 7.94 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
    </svg>
    <span class="text-sm font-semibold">Chat</span>
    <span class="w-5 h-5 rounded-full bg-cream-50 text-maroon-800 text-[10px] font-bold grid place-items-center">2</span>
</a>