
<footer class="lm-footer">

    <div class="lm-footer-shell">

        <div class="lm-footer-grid">

            {{-- LUMEAR BRAND --}}
            <div class="lm-footer-brand">

                <a href="{{ url('/home') }}"
                    class="lm-footer-logo">

                    <img src="{{ asset('images/lumear-logo.png') }}"
                        alt="LUMEAR logo">

                    <span>Lumear</span>
                </a>

                <p class="lm-footer-description">
                    A marketplace for the everyday and the extraordinary.<br>
                    Discover thoughtfully selected finds across every category.
                </p>

                <div class="lm-social-icons">
                    <span aria-label="Facebook">f</span>
                    <span aria-label="Instagram">◎</span>
                    <span aria-label="TikTok">♪</span>
                    <span aria-label="YouTube">▶</span>
                </div>

            </div>

            {{-- CUSTOMER CARE --}}
            <div class="lm-footer-column">

                <h3>Customer Care</h3>

                <a href="{{ url('/chat') }}">Help Center</a>
                <a href="{{ url('/orders') }}">Track My Order</a>
                <a href="{{ url('/chat') }}">Returns & Refunds</a>
                <a href="{{ url('/chat') }}">Contact Us</a>

            </div>

            {{-- EXPLORE --}}
            <div class="lm-footer-column">

                <h3>Explore Lumear</h3>

                <a href="{{ url('/search') }}">Browse Products</a>
                <a href="{{ url('/search?sort=newest') }}">Featured Collections</a>
                <a href="{{ url('/search') }}">Flash Deals</a>
                <a href="{{ url('/account') }}">My Account</a>

            </div>

            {{-- PAYMENTS --}}
            <div class="lm-footer-column">

                <h3>Payment Options</h3>

                <div class="lm-payment-badges">
                    <span>Cash On Delivery</span>
                </div>

                <p class="lm-payment-note">
                    Payment integration will be enabled during checkout development.
                </p>

            </div>

        </div>

    </div>

    <div class="lm-footer-bottom">

        <div class="lm-footer-shell lm-footer-bottom-inner">

            <span>
                © {{ date('Y') }} Lumear · Academic development project.
            </span>

            <span>
                Made for better everyday discoveries.
            </span>

        </div>
    </div>

</footer>


{{-- LUMEAR FLOATING CUSTOMER SUPPORT --}}
<a href="{{ url('/chat') }}"
   class="lumear-support-float"
   aria-label="Open Lumear customer support chat"
   title="Chat with Lumear">

    <span class="lumear-support-icon">
        <svg viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="1.8"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">
            <path d="M20 11.5a8 8 0 0 1-8 8 8.5 8.5 0 0 1-3.4-.7L4 20l1.2-4.4A8 8 0 1 1 20 11.5Z"/>
            <path d="M8.5 11.5h7"/>
            <path d="M8.5 14.5h4.5"/>
        </svg>

        <span class="lumear-support-status"></span>
    </span>

    <span class="lumear-support-label">
        <strong>Need help?</strong>
        <small>Chat with us</small>
    </span>

    <span class="lumear-support-arrow" aria-hidden="true">↗</span>
</a>

