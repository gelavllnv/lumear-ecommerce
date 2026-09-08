{{-- Expects: $product = ['name','price','old_price'=>null,'discount'=>null,'emoji','rating','sold','bg'=>'sand-400/30'] --}}
<a href="{{ url('/product/'.($product['slug'] ?? '1')) }}"
   class="group block rounded-lg bg-cream-50 border border-sand-400/40 overflow-hidden hover:shadow-lift hover:-translate-y-0.5 transition-all duration-200">
    <div class="relative aspect-square bg-{{ $product['bg'] ?? 'sand-400/25' }} grid place-items-center overflow-hidden">
        <span class="text-5xl group-hover:scale-110 transition-transform duration-300">{{ $product['emoji'] }}</span>
        @if(!empty($product['discount']))
            <span class="absolute top-0 left-0 bg-maroon-700 text-cream-50 text-[11px] font-bold px-2 py-1 rounded-br-md">-{{ $product['discount'] }}%</span>
        @endif
        <button class="absolute top-2 right-2 w-7 h-7 rounded-md bg-cream-50/85 backdrop-blur grid place-items-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-maroon-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/>
            </svg>
        </button>
    </div>
    <div class="p-3">
        <p class="text-sm text-maroon-900 leading-snug line-clamp-2 min-h-[2.5rem]">{{ $product['name'] }}</p>
        <div class="flex items-baseline gap-2 mt-2">
            <span class="text-maroon-700 font-extrabold">₱{{ $product['price'] }}</span>
            @if(!empty($product['old_price']))
                <span class="text-xs text-maroon-900/40 line-through">₱{{ $product['old_price'] }}</span>
            @endif
        </div>
        <div class="flex items-center gap-1.5 mt-1.5 text-[11px] text-maroon-900/50">
            <span class="text-gold-500">★ {{ $product['rating'] ?? '4.8' }}</span>
            <span>·</span>
            <span>{{ $product['sold'] ?? '1.2k' }} sold</span>
        </div>
    </div>
</a>