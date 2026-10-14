
document.addEventListener("DOMContentLoaded", () => {
    const root = document.getElementById("lumearProduct");
    if (!root) return;

    const $ = (selector) => root.querySelector(selector);
    const $$ = (selector) => [...root.querySelectorAll(selector)];

    let product;

    try {
        product = JSON.parse(root.dataset.product);
    } catch (error) {
        console.error("Unable to load product information.", error);
        return;
    }

    const gallery = window.lumearPdpGallery || [product.image];
    const maxStock = Math.max(0, Number(product.stock) || 0);

    let activeImage = 0;
    let quantity = 1;

    let selectedColor =
        $("#pdpColors .active")?.dataset.value || "Classic";

    let selectedSize =
        $("#pdpSizes .active")?.dataset.value || "Standard";

    const CART_KEY = "lumear_buyer_cart_demo_v1";
    const SAVED_KEY = "lumear_buyer_saved_demo_v1";

    let toastTimer;

    function readStorage(key) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || "[]");
            return Array.isArray(value) ? value : [];
        } catch (error) {
            return [];
        }
    }

    function saveStorage(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            showToast("Browser storage is unavailable.");
            return false;
        }
    }

    function showToast(message) {
        const toast = $("#pdpToast");
        if (!toast) return;

        clearTimeout(toastTimer);

        toast.textContent = message;
        toast.classList.add("visible");

        toastTimer = setTimeout(() => {
            toast.classList.remove("visible");
        }, 3000);
    }

    /* ==========================================
       PRODUCT IMAGE GALLERY
       ========================================== */

    function setImage(index) {
        if (!gallery.length) return;

        activeImage = Math.max(
            0,
            Math.min(index, gallery.length - 1)
        );

        const mainImage = $("#pdpMainImage");

        if (mainImage) {
            mainImage.src = gallery[activeImage];
            mainImage.alt = `${product.name} — image ${activeImage + 1}`;
        }

        const counter = $("#pdpImageCounter");

        if (counter) {
            counter.textContent = String(
                activeImage + 1
            ).padStart(2, "0");
        }

        $$(".pdp-thumbnail").forEach((button, i) => {
            const isActive = i === activeImage;

            button.classList.toggle("active", isActive);
            button.setAttribute("aria-pressed", String(isActive));
        });
    }

    $$(".pdp-thumbnail").forEach((button) => {
        button.addEventListener("click", () => {
            setImage(Number(button.dataset.index));
        });
    });

    setImage(0);

    /* ==========================================
       IMAGE LIGHTBOX
       ========================================== */

    const lightbox = $("#pdpLightbox");
    const lightboxImage = $("#pdpLightboxImage");

    function closeLightbox() {
        if (!lightbox) return;

        lightbox.classList.remove("open");
        lightbox.setAttribute("aria-hidden", "true");

        document.body.style.overflow = "";
    }

    $("#pdpZoom")?.addEventListener("click", () => {
        if (!lightbox || !lightboxImage) return;

        lightboxImage.src = gallery[activeImage];
        lightboxImage.alt = product.name;

        lightbox.classList.add("open");
        lightbox.setAttribute("aria-hidden", "false");

        document.body.style.overflow = "hidden";
    });

    $("#pdpCloseLightbox")?.addEventListener(
        "click",
        closeLightbox
    );

    lightbox?.addEventListener("click", (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeLightbox();
        }
    });

    /* ==========================================
       PRODUCT VARIATIONS
       ========================================== */

    function setupOptions(containerSelector, labelSelector, callback) {
        const container = $(containerSelector);
        const label = $(labelSelector);

        if (!container) return;

        const buttons = [
            ...container.querySelectorAll(".pdp-option")
        ];

        buttons.forEach((button) => {
            button.addEventListener("click", () => {
                buttons.forEach((item) => {
                    item.classList.remove("active");
                    item.setAttribute("aria-pressed", "false");
                });

                button.classList.add("active");
                button.setAttribute("aria-pressed", "true");

                const value = button.dataset.value;

                if (label) {
                    label.textContent = value;
                }

                callback(value);
            });
        });
    }

    setupOptions(
        "#pdpColors",
        "#pdpSelectedColor",
        (value) => {
            selectedColor = value;
        }
    );

    setupOptions(
        "#pdpSizes",
        "#pdpSelectedSize",
        (value) => {
            selectedSize = value;
        }
    );

    /* ==========================================
       QUANTITY SELECTOR
       ========================================== */

    const quantityDisplay = $("#pdpQuantity");
    const decreaseButton = $("#pdpDecrease");
    const increaseButton = $("#pdpIncrease");

    function updateQuantity() {
        quantity = Math.max(
            1,
            Math.min(quantity, Math.max(1, maxStock))
        );

        if (quantityDisplay) {
            quantityDisplay.textContent = quantity;
        }

        if (decreaseButton) {
            decreaseButton.disabled = quantity <= 1;
        }

        if (increaseButton) {
            increaseButton.disabled =
                maxStock === 0 || quantity >= maxStock;
        }
    }

    decreaseButton?.addEventListener("click", () => {
        quantity--;
        updateQuantity();
    });

    increaseButton?.addEventListener("click", () => {
        quantity++;
        updateQuantity();
    });

    updateQuantity();

    /* ==========================================
       ADD TO CART
       Demo only — stored in browser localStorage
       ========================================== */

    function addToCart() {
        if (maxStock < 1) {
            showToast("This product is currently out of stock.");
            return false;
        }

        const cart = readStorage(CART_KEY);

        const itemId = [
            product.id,
            selectedColor,
            selectedSize
        ].join("::");

        const existing = cart.find(
            (item) => item.id === itemId
        );

        if (existing) {
            const current = Number(existing.quantity) || 0;

            if (current + quantity > maxStock) {
                showToast(
                    `Only ${maxStock} pieces are available for this item.`
                );
                return false;
            }

            existing.quantity = current + quantity;
        } else {
            cart.push({
                id: itemId,
                productId: product.id,
                slug: product.slug,
                name: product.name,
                price: Number(product.price),
                image: product.image,
                seller: product.seller,
                color: selectedColor,
                size: selectedSize,
                quantity: quantity,
                stock: maxStock,
                selected: true
            });
        }

        if (!saveStorage(CART_KEY, cart)) {
            return false;
        }

        showToast(`${quantity} item(s) added to your cart.`);

        // Inform other Buyer components about the change.
        window.dispatchEvent(
            new CustomEvent("lumear:cart-updated", {
                detail: { cart }
            })
        );

        // Update the navbar badge immediately.
        const cartBadge = document.getElementById(
            "lumearNavCartCount"
        );

        if (cartBadge) {
            const count = cart.reduce(
                (sum, item) => sum + (Number(item.quantity) || 0),
                0
            );

            cartBadge.textContent = count > 99 ? "99+" : count;
            cartBadge.hidden = count === 0;
        }

        return true;
    }

    $("#pdpAddCart")?.addEventListener("click", addToCart);

    /* ==========================================
       BUY NOW
       ========================================== */

    $("#pdpBuyNow")?.addEventListener("click", () => {
        const added = addToCart();

        if (!added) return;

        // Checkout isn't connected yet, so direct
        // the buyer to the existing cart page.
        window.location.href = "/cart";
    });

    /* ==========================================
       SAVE TO FAVORITES
       ========================================== */

    const saveButton = $("#pdpSave");

    function updateSavedButton() {
        if (!saveButton) return;

        const saved = readStorage(SAVED_KEY);

        const isSaved = saved.some(
            (item) => item.id === product.id
        );

        saveButton.classList.toggle("saved", isSaved);

        const label = saveButton.querySelector("span");

        if (label) {
            label.textContent = isSaved
                ? "Saved to Favorites"
                : "Save to Favorites";
        }

        saveButton.setAttribute(
            "aria-pressed",
            String(isSaved)
        );
    }

    saveButton?.addEventListener("click", () => {
        const saved = readStorage(SAVED_KEY);

        const index = saved.findIndex(
            (item) => item.id === product.id
        );

        if (index >= 0) {
            saved.splice(index, 1);

            if (saveStorage(SAVED_KEY, saved)) {
                showToast("Removed from favorites.");
            }
        } else {
            saved.push({
                id: product.id,
                slug: product.slug,
                name: product.name,
                price: Number(product.price),
                image: product.image
            });

            if (saveStorage(SAVED_KEY, saved)) {
                showToast("Product saved to favorites.");
            }
        }

        updateSavedButton();
    });

    updateSavedButton();

    /* ==========================================
       PRODUCT INFORMATION TABS
       ========================================== */

    const tabButtons = $$(".pdp-tab-bar button");
    const tabPanels = $$(".pdp-tab-content");

    tabButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const target = button.dataset.tab;

            tabButtons.forEach((tab) => {
                const active = tab === button;

                tab.classList.toggle("active", active);
                tab.setAttribute("aria-selected", String(active));
            });

            tabPanels.forEach((panel) => {
                panel.classList.toggle(
                    "active",
                    panel.dataset.panel === target
                );
            });
        });
    });
});
