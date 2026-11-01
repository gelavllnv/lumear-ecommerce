
document.addEventListener("DOMContentLoaded", () => {
    const CART_KEY = "lumear_buyer_cart_demo_v1";

    function updateCartBadge() {
        const badge = document.getElementById("lumearNavCartCount");

        if (!badge) return;

        let count = 0;

        try {
            const cart = JSON.parse(
                localStorage.getItem(CART_KEY) || "[]"
            );

            if (Array.isArray(cart)) {
                count = cart.reduce((total, item) => {
                    return total + Math.max(
                        0,
                        Number(item.quantity) || 0
                    );
                }, 0);
            }
        } catch (error) {
            count = 0;
        }

        badge.textContent = count > 99 ? "99+" : String(count);
        badge.hidden = count === 0;
    }

    updateCartBadge();

    window.addEventListener("storage", event => {
        if (event.key === CART_KEY) {
            updateCartBadge();
        }
    });

    document.addEventListener("click", event => {
        if (event.target.closest(
            ".catalog-cart-button, #catalogModalAdd"
        )) {
            setTimeout(updateCartBadge, 0);
        }
    });
});
