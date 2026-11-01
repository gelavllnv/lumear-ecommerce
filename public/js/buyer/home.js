
document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const $ = id => document.getElementById(id);

    const CART_KEY = "lumear_buyer_cart_demo_v1";
    const WISHLIST_KEY = "lumear_buyer_wishlist_demo_v1";
    const TIMER_KEY = "lumear_buyer_deal_ends_v1";

    const money = value =>
        "₱" + Number(value).toLocaleString("en-PH");

    function readList(key) {
        try {
            const result = JSON.parse(localStorage.getItem(key));
            return Array.isArray(result) ? result : [];
        } catch {
            return [];
        }
    }

    let cart = readList(CART_KEY);
    let wishlist = readList(WISHLIST_KEY);
    let toastTimer = null;

    function toast(message) {
        const element = $("buyerToast");
        if (!element) return;

        element.textContent = message;
        element.classList.add("show");

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            element.classList.remove("show");
        }, 2800);
    }

    function getProduct(card) {
        return {
            id: card.dataset.id,
            name: card.dataset.name,
            seller: card.dataset.seller,
            price: Number(card.dataset.price),
            image: card.dataset.image,
            stock: Number(card.dataset.stock)
        };
    }

    // CART

    function updateCartCount() {
        const badge = $("cartCount");
        if (!badge) return;

        const total = cart.reduce(
            (sum, item) => sum + Number(item.quantity || 0),
            0
        );

        badge.textContent = total > 99 ? "99+" : total;
        badge.hidden = total === 0;
    }

    function addToCart(product) {
        const existing = cart.find(item => item.id === product.id);

        if (existing) {
            if (existing.quantity >= product.stock) {
                toast("Maximum available demo stock reached.");
                return;
            }

            existing.quantity += 1;
        } else {
            if (product.stock <= 0) {
                toast("This product is out of stock.");
                return;
            }

            cart.push({
                ...product,
                quantity: 1,
                selected: true
            });
        }

        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        updateCartCount();
        toast(product.name + " added to cart.");
    }

    // SAVED PRODUCTS

    function updateWishlist() {
        const badge = $("wishlistCount");

        if (badge) {
            badge.textContent = wishlist.length;
            badge.hidden = wishlist.length === 0;
        }

        document.querySelectorAll(".lumear-home .product-card")
            .forEach(card => {
                const button = card.querySelector(".wishlist-button");
                if (!button) return;

                const saved = wishlist.some(
                    item => item.id === card.dataset.id
                );

                button.classList.toggle("saved", saved);
                button.textContent = saved ? "♥" : "♡";
                button.setAttribute("aria-pressed", String(saved));
            });

        const content = $("wishlistPanelContent");
        if (!content) return;

        content.replaceChildren();

        if (wishlist.length === 0) {
            const empty = document.createElement("p");
            empty.textContent = "You haven't saved any products yet.";
            content.appendChild(empty);
            return;
        }

        wishlist.forEach(product => {
            const row = document.createElement("div");
            row.className = "wishlist-entry";

            const image = document.createElement("img");
            image.src = product.image;
            image.alt = product.name;

            const details = document.createElement("div");
            const name = document.createElement("strong");
            const price = document.createElement("small");

            name.textContent = product.name;
            price.textContent = money(product.price);

            details.append(name, price);
            row.append(image, details);
            content.appendChild(row);
        });
    }

    function toggleWishlist(product) {
        const index = wishlist.findIndex(
            item => item.id === product.id
        );

        if (index >= 0) {
            wishlist.splice(index, 1);
            toast("Removed from saved products.");
        } else {
            wishlist.push(product);
            toast("Added to saved products.");
        }

        localStorage.setItem(WISHLIST_KEY, JSON.stringify(wishlist));
        updateWishlist();
    }

    // QUICK VIEW

    let previewProduct = null;

    function openQuickView(product) {
        previewProduct = product;

        $("quickViewTitle").textContent = product.name;
        $("quickViewSeller").textContent = "Sold by " + product.seller;
        $("quickViewPrice").textContent = money(product.price);
        $("quickViewStock").textContent =
            product.stock + " available (demo)";

        $("quickViewImage").src = product.image;
        $("quickViewImage").alt = product.name;

        $("quickViewBackdrop").hidden = false;
        document.body.classList.add("modal-open");
        $("quickViewClose").focus();
    }

    function closeQuickView() {
        $("quickViewBackdrop").hidden = true;
        document.body.classList.remove("modal-open");
        previewProduct = null;
    }

    $("quickViewClose")?.addEventListener("click", closeQuickView);

    $("quickViewBackdrop")?.addEventListener("click", event => {
        if (event.target === $("quickViewBackdrop")) {
            closeQuickView();
        }
    });

    $("quickViewAddCart")?.addEventListener("click", () => {
        if (!previewProduct) return;

        addToCart(previewProduct);
        closeQuickView();
    });

    document.querySelectorAll(".lumear-home .product-card")
        .forEach(card => {
            const product = getProduct(card);

            card.querySelector(".add-cart-button")
                ?.addEventListener("click", () => addToCart(product));

            card.querySelector(".wishlist-button")
                ?.addEventListener("click", () => toggleWishlist(product));

            card.querySelector(".quick-view-button")
                ?.addEventListener("click", () => openQuickView(product));
        });

    // HERO CAROUSEL

    const slider = $("heroSlider");
    const track = $("heroTrack");
    const slides = [...document.querySelectorAll(".lumear-home .hero-slide")];
    const dots = [...document.querySelectorAll("#heroPagination button")];
    const progress = $("heroProgress");

    let currentSlide = 0;
    let slideTimer = null;
    let paused = false;
    const DURATION = 6000;

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    ).matches;

    function showSlide(index) {
        if (!track || !slides.length) return;

        currentSlide = (index + slides.length) % slides.length;

        track.style.transform =
            `translate3d(-${currentSlide * 100}%, 0, 0)`;

        slides.forEach((slide, i) => {
            slide.classList.toggle("active", i === currentSlide);
            slide.setAttribute("aria-hidden", String(i !== currentSlide));
        });

        dots.forEach((dot, i) => {
            const active = i === currentSlide;
            dot.classList.toggle("active", active);
            dot.setAttribute("aria-current", String(active));
        });

        if ($("heroSlideCounter")) {
            $("heroSlideCounter").textContent =
                String(currentSlide + 1).padStart(2, "0") +
                " / " +
                String(slides.length).padStart(2, "0");
        }
    }

    function stopCarousel() {
        clearTimeout(slideTimer);
        slideTimer = null;

        if (progress) {
            progress.classList.remove("running");
            progress.style.width = "0";
        }
    }

    function scheduleNext() {
        stopCarousel();

        if (paused || reducedMotion || document.hidden) return;

        if (progress) {
            void progress.offsetWidth;
            progress.classList.add("running");
        }

        slideTimer = setTimeout(() => {
            showSlide(currentSlide + 1);
            scheduleNext();
        }, DURATION);
    }

    function goToSlide(index) {
        showSlide(index);
        scheduleNext();
    }

    $("heroPrev")?.addEventListener("click", () => {
        goToSlide(currentSlide - 1);
    });

    $("heroNext")?.addEventListener("click", () => {
        goToSlide(currentSlide + 1);
    });

    dots.forEach((dot, index) => {
        dot.addEventListener("click", () => goToSlide(index));
    });

    slider?.addEventListener("mouseenter", () => {
        paused = true;
        stopCarousel();
    });

    slider?.addEventListener("mouseleave", () => {
        paused = false;
        scheduleNext();
    });

    slider?.addEventListener("focusin", () => {
        paused = true;
        stopCarousel();
    });

    slider?.addEventListener("focusout", event => {
        if (!slider.contains(event.relatedTarget)) {
            paused = false;
            scheduleNext();
        }
    });

    document.addEventListener("visibilitychange", scheduleNext);

    let touchStartX = null;

    slider?.addEventListener("touchstart", event => {
        touchStartX = event.touches[0]?.clientX ?? null;
    }, { passive: true });

    slider?.addEventListener("touchend", event => {
        if (touchStartX === null) return;

        const endX = event.changedTouches[0]?.clientX;
        if (endX === undefined) return;

        const difference = endX - touchStartX;

        if (Math.abs(difference) > 60) {
            goToSlide(currentSlide + (difference < 0 ? 1 : -1));
        }

        touchStartX = null;
    }, { passive: true });

    showSlide(0);
    scheduleNext();

    // FLASH SALE COUNTDOWN

    function getDealEndTime() {
        const saved = Number(sessionStorage.getItem(TIMER_KEY));

        if (saved > Date.now()) return saved;

        const end = Date.now() + 2 * 60 * 60 * 1000;
        sessionStorage.setItem(TIMER_KEY, String(end));

        return end;
    }

    const dealEndTime = getDealEndTime();

    function updateCountdown() {
        const remaining = Math.max(0, dealEndTime - Date.now());
        const seconds = Math.floor(remaining / 1000);

        if ($("countHours")) {
            $("countHours").textContent =
                String(Math.floor(seconds / 3600)).padStart(2, "0");
        }

        if ($("countMinutes")) {
            $("countMinutes").textContent =
                String(Math.floor((seconds % 3600) / 60)).padStart(2, "0");
        }

        if ($("countSeconds")) {
            $("countSeconds").textContent =
                String(seconds % 60).padStart(2, "0");
        }
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);

    // CATEGORY MENU

    const categoryButton = $("allCategoriesToggle");
    const categoryDropdown = $("categoryDropdown");

    categoryButton?.addEventListener("click", () => {
        const open = categoryDropdown.classList.toggle("open");
        categoryButton.setAttribute("aria-expanded", String(open));
    });

    // MOBILE NAVIGATION

    $("mobileMenuToggle")?.addEventListener("click", () => {
        const nav = $("categoryNav");
        const open = nav.classList.toggle("mobile-open");

        $("mobileMenuToggle").setAttribute(
            "aria-expanded",
            String(open)
        );
    });

    // PANELS

    function closePanels() {
        if ($("notificationPanel")) $("notificationPanel").hidden = true;
        if ($("wishlistPanel")) $("wishlistPanel").hidden = true;
    }

    $("notificationToggle")?.addEventListener("click", () => {
        const wasHidden = $("notificationPanel").hidden;
        closePanels();
        $("notificationPanel").hidden = !wasHidden;
    });

    $("wishlistToggle")?.addEventListener("click", () => {
        const wasHidden = $("wishlistPanel").hidden;
        closePanels();
        $("wishlistPanel").hidden = !wasHidden;
    });

    $("notificationClose")?.addEventListener("click", () => {
        $("notificationPanel").hidden = true;
    });

    $("wishlistClose")?.addEventListener("click", () => {
        $("wishlistPanel").hidden = true;
    });

    document.addEventListener("click", event => {
        if (!event.target.closest(".category-menu-wrap")) {
            categoryDropdown?.classList.remove("open");
            categoryButton?.setAttribute("aria-expanded", "false");
        }

        if (
            !event.target.closest("#notificationPanel") &&
            !event.target.closest("#notificationToggle") &&
            !event.target.closest("#wishlistPanel") &&
            !event.target.closest("#wishlistToggle")
        ) {
            closePanels();
        }
    });

    document.addEventListener("keydown", event => {
        if (event.key !== "Escape") return;

        closePanels();
        closeQuickView();
        categoryDropdown?.classList.remove("open");
        categoryButton?.setAttribute("aria-expanded", "false");
    });

    // UPDATE COUNTS WHEN STORAGE CHANGES

    window.addEventListener("storage", event => {
        if (event.key === CART_KEY) {
            cart = readList(CART_KEY);
            updateCartCount();
        }

        if (event.key === WISHLIST_KEY) {
            wishlist = readList(WISHLIST_KEY);
            updateWishlist();
        }
    });

    updateCartCount();
    updateWishlist();
});
