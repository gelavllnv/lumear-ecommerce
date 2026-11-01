
/* ==========================================================
   LUMEAR HOMEPAGE — DIRECT PRODUCT NAVIGATION
   Flash Deals + Recommended Products
   ========================================================== */

(function () {
    "use strict";

    const PRODUCT_PATH = "/product/";

    function getSlugFromLink(link) {
        if (!link) return null;

        try {
            const url = new URL(link.href, window.location.origin);
            const match = url.pathname.match(/^\/product\/([^/]+)\/?$/);

            return match ? decodeURIComponent(match[1]) : null;
        } catch {
            return null;
        }
    }

    function findProductSlug(element) {
        if (!element) return null;

        // Check the clicked element and its closest product card.
        const card = element.closest(
            "[data-product-slug], [data-slug], [data-product-card], " +
            ".product-card, .flash-product-card, .deal-card"
        );

        if (!card) return null;

        // Product slug stored directly on the card.
        const directSlug =
            card.dataset.productSlug ||
            card.dataset.slug ||
            card.getAttribute("data-product");

        if (directSlug) return directSlug;

        // Product slug stored on a child element.
        const child = card.querySelector(
            "[data-product-slug], [data-slug]"
        );

        if (child) {
            const childSlug =
                child.dataset.productSlug ||
                child.dataset.slug;

            if (childSlug) return childSlug;
        }

        // Product Details link already exists inside the card.
        const link = card.querySelector('a[href*="/product/"]');

        return getSlugFromLink(link);
    }

    function openProduct(slug) {
        if (!slug) return;

        window.location.href =
            PRODUCT_PATH + encodeURIComponent(String(slug).trim());
    }

    document.addEventListener("click", function (event) {
        const target = event.target;

        if (!(target instanceof Element)) return;

        // Do not interfere with other homepage features.
        if (
            target.closest(
                "input, select, textarea, " +
                "[data-add-to-cart], [data-cart], " +
                "[data-favorite], [data-wishlist], " +
                "[data-save-product], [data-filter]"
            )
        ) {
            return;
        }

        // Existing Quick View triggers.
        const quickView = target.closest(
            "[data-quick-view], [data-quickview], " +
            "[data-product-preview], [data-preview], " +
            ".quick-view-btn, .quick-view-button"
        );

        if (quickView) {
            const slug =
                quickView.dataset.productSlug ||
                quickView.dataset.slug ||
                findProductSlug(quickView);

            if (slug) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openProduct(slug);
            }

            return;
        }

        // Product cards and their images/titles.
        const card = target.closest(
            "[data-product-card], [data-product-slug], [data-slug], " +
            ".product-card, .flash-product-card, .deal-card"
        );

        if (!card) return;

        // Keep Add to Cart, wishlist, and other action buttons working.
        if (target.closest("button")) return;

        // Keep links to other destinations working.
        const link = target.closest("a");

        if (link && !link.href.includes("/product/")) {
            return;
        }

        const slug = findProductSlug(card);

        if (slug) {
            event.preventDefault();
            event.stopImmediatePropagation();
            openProduct(slug);
        }
    }, true);

})();
