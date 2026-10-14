
/* ============================================================
   LUMEAR BUYER — CATALOG PRODUCT NAVIGATION
   Opens the full product details page instead of Quick View.
   ============================================================ */

(function () {
    "use strict";

    const PRODUCT_ROUTE = "/product/";

    function productUrl(slug) {
        if (!slug || typeof slug !== "string") {
            return null;
        }

        return PRODUCT_ROUTE + encodeURIComponent(slug.trim());
    }

    function getProductSlug(element) {
        if (!element) return null;

        // Supports product cards with data-slug or data-product-slug.
        const card = element.closest(
            "[data-product-slug], [data-slug], [data-product-card]"
        );

        if (card) {
            const slug =
                card.dataset.productSlug ||
                card.dataset.slug ||
                card.querySelector("[data-product-slug]")?.dataset.productSlug ||
                card.querySelector("[data-slug]")?.dataset.slug;

            if (slug) return slug;
        }

        // Supports existing product links.
        const link = element.closest('a[href*="/product/"]');

        if (link) {
            const path = new URL(link.href, window.location.origin).pathname;
            const match = path.match(/\/product\/([^/]+)/);

            if (match) {
                return decodeURIComponent(match[1]);
            }
        }

        return null;
    }

    function openProduct(slug) {
        const url = productUrl(slug);

        if (url) {
            window.location.assign(url);
        }
    }

    document.addEventListener(
        "click",
        function (event) {
            const target = event.target;

            if (!(target instanceof Element)) return;

            // Do not interrupt actual cart, favorite, or filter actions.
            if (
                target.closest(
                    '[data-add-to-cart], [data-favorite], ' +
                    '[data-wishlist], [data-filter], ' +
                    'input, select, textarea'
                )
            ) {
                return;
            }

            // Detect existing Quick View buttons.
            const quickView = target.closest(
                '[data-quick-view], [data-quickview], ' +
                '[data-preview], [data-product-preview], ' +
                '.quick-view-btn, .quick-view-button'
            );

            if (quickView) {
                const slug =
                    quickView.dataset.productSlug ||
                    quickView.dataset.slug ||
                    getProductSlug(quickView);

                if (slug) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    openProduct(slug);
                    return;
                }
            }

            // Product card clicks should navigate to Product Details.
            const card = target.closest(
                "[data-product-card], [data-product-slug], [data-slug]"
            );

            if (!card) return;

            // Respect other buttons and links inside the card.
            if (
                target.closest("button") &&
                !target.closest(
                    '[data-quick-view], [data-quickview], [data-preview]'
                )
            ) {
                return;
            }

            const link = target.closest("a");

            if (link && !link.href.includes("/product/")) {
                return;
            }

            const slug = getProductSlug(card);

            if (slug) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openProduct(slug);
            }
        },
        true
    );

    // Close any existing Quick View popup on page load.
    document.addEventListener("DOMContentLoaded", function () {
        const preview = document.querySelector(
            '[data-quick-view-modal], [data-product-preview-modal]'
        );

        if (preview) {
            preview.hidden = true;
        }
    });
})();
