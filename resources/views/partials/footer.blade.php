<footer class="hidden md:block bg-maroon-950 text-cream-100/70 mt-20">
    <div class="max-w-7xl mx-auto px-6 py-14 grid grid-cols-4 gap-10">
        <div class="col-span-1">
            <span class="font-display italic text-2xl text-cream-50">Lumear</span>
            <p class="text-sm mt-3 leading-relaxed max-w-[26ch]">A marketplace for the everyday and the extraordinary — twelve worlds of goods, one cart.</p>
        </div>
        <div>
            <h4 class="text-cream-50 text-sm font-semibold mb-3">Customer Care</h4>
            <ul class="space-y-2 text-sm">
                <li><a href="#" class="link-grow hover:text-cream-50">Help Center</a></li>
                <li><a href="#" class="link-grow hover:text-cream-50">Track My Order</a></li>
                <li><a href="#" class="link-grow hover:text-cream-50">Returns & Refunds</a></li>
                <li><a href="#" class="link-grow hover:text-cream-50">Contact Us</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-cream-50 text-sm font-semibold mb-3">About Lumear</h4>
            <ul class="space-y-2 text-sm">
                <li><a href="#" class="link-grow hover:text-cream-50">Our Story</a></li>
                <li><a href="#" class="link-grow hover:text-cream-50">Sell on Lumear</a></li>
                <li><a href="#" class="link-grow hover:text-cream-50">Careers</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-cream-50 text-sm font-semibold mb-3">Payments We Accept</h4>
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach (['Cash On Delivery','GCash'] as $p)
                    <span class="px-2.5 py-1 rounded-md bg-cream-100/10 border border-cream-100/10">{{ $p }}</span>
                @endforeach
            </div>
        </div>
    </div>
    <div class="border-t border-cream-100/10 py-5 text-center text-xs text-cream-100/40">
        © {{ date('Y') }} Lumear. Built for the final project, not for production &mdash; yet.
    </div>
</footer>