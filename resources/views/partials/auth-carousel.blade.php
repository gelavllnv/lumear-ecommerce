{{-- Left-side marketing carousel for login/register. Auto-advances, dots are clickable. --}}
<div x-data="authCarousel()" x-init="start()" class="hidden lg:flex flex-col items-center justify-center relative z-10 w-[420px] shrink-0">

    <div class="relative w-72 h-[420px]">
        <template x-for="(slide, i) in slides" :key="i">
            <div x-show="active === i"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 translate-y-3"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute inset-0">

                {{-- "Phone" frame standing in for an app screenshot --}}
                <div class="w-full h-full rounded-[2.5rem] bg-cream-50/95 border-[6px] border-maroon-950/40 shadow-lift overflow-hidden flex flex-col">
                    <div class="h-6 flex items-center justify-center shrink-0">
                        <div class="w-16 h-1.5 rounded-full bg-maroon-900/15"></div>
                    </div>
                    <div class="flex-1 grid place-items-center text-7xl" x-text="slide.emoji"></div>
                    <div class="px-6 pb-8">
                        <div class="h-2 w-3/4 rounded-full bg-maroon-900/10 mb-2"></div>
                        <div class="h-2 w-1/2 rounded-full bg-maroon-900/10"></div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="mt-8 text-center">
        <template x-for="(slide, i) in slides" :key="'t'+i">
            <div x-show="active === i">
                <p class="font-display italic text-2xl text-cream-50" x-text="slide.title"></p>
                <p class="text-sm text-cream-100/60 mt-2 max-w-[26ch] mx-auto" x-text="slide.copy"></p>
            </div>
        </template>
    </div>

    <div class="flex items-center gap-2 mt-6">
        <template x-for="(slide, i) in slides" :key="'d'+i">
            <button @click="active = i" class="h-1.5 rounded-full transition-all duration-300" :class="active === i ? 'w-6 bg-gold-400' : 'w-1.5 bg-cream-50/30'"></button>
        </template>
    </div>
</div>

<script>
    function authCarousel() {
        return {
            active: 0,
            slides: [
                { emoji: '🚚', title: 'Free shipping', copy: 'On your first order across every category, every week.' },
                { emoji: '⚡', title: 'Flash deals', copy: 'New drops every hour — limited stock, real savings.' },
                { emoji: '🛍️', title: '12 categories', copy: 'From pet bowls to gold hoops, all under one roof.' },
                { emoji: '📦', title: 'Live tracking', copy: 'Watch your order move from shop to doorstep.' },
            ],
            start() {
                setInterval(() => { this.active = (this.active + 1) % this.slides.length }, 4000);
            }
        }
    }
</script>