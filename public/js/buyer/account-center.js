
document.addEventListener("DOMContentLoaded", () => {
    const PROFILE_KEY = "lumear_buyer_profile_demo_v1";
    const ADDRESS_KEY = "lumear_buyer_addresses_demo_v1";
    const PREF_KEY = "lumear_buyer_preferences_demo_v1";
    const SAVED_KEY = "lumear_buyer_saved_demo_v1";
    const ORDERS_KEY = "lumear_buyer_orders_demo_v1";

    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];

    const read = (key, fallback) => {
        try {
            const value = JSON.parse(localStorage.getItem(key));
            return value === null ? fallback : value;
        } catch {
            return fallback;
        }
    };

    const write = (key, value) => {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            toast("Unable to save changes in this browser.");
            return false;
        }
    };

    const escapeHTML = value => String(value ?? "").replace(/[&<>"']/g, c => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    }[c]));

    const money = value => "₱" + (Number(value) || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const productLink = slug => slug
        ? "/product/" + encodeURIComponent(slug)
        : "/search";

    const imageURL = value => {
        try {
            const url = new URL(String(value), location.origin);
            return ["http:", "https:"].includes(url.protocol) ? url.href : "";
        } catch {
            return "";
        }
    };

    let toastTimer;

    function toast(message) {
        const el = $("#lacToast");
        if (!el) return;
        clearTimeout(toastTimer);
        el.textContent = message;
        el.classList.add("visible");
        toastTimer = setTimeout(() => el.classList.remove("visible"), 2700);
    }

    /* =========================================
       MY ACCOUNT
       ========================================= */

    const account = $("#lumearAccount");

    if (account) {
        const validTabs = ["profile", "addresses", "notifications", "security"];

        function showTab(tab, updateHash = true) {
            if (!validTabs.includes(tab)) tab = "profile";

            $$("[data-account-tab]", account).forEach(button => {
                button.classList.toggle("active", button.dataset.accountTab === tab);
            });

            $$("[data-account-panel]", account).forEach(panel => {
                panel.classList.toggle("active", panel.dataset.accountPanel === tab);
            });

            if (updateHash) history.replaceState(null, "", "#" + tab);
        }

        $$("[data-account-tab]", account).forEach(button => {
            button.addEventListener("click", () => showTab(button.dataset.accountTab));
        });

        window.addEventListener("hashchange", () => {
            showTab(location.hash.slice(1), false);
        });

        showTab(location.hash.slice(1), false);

        /* PROFILE */

        const profileForm = $("#lacProfileForm");
        const profileDefaults = {
            firstName: "Juana",
            lastName: "Dela Cruz",
            middleInitial: "",
            gender: "",
            email: "juana@example.com",
            phone: ""
        };

        const profile = {
            ...profileDefaults,
            ...read(PROFILE_KEY, {})
        };

        Object.entries(profile).forEach(([key, value]) => {
            if (profileForm.elements.namedItem(key)) {
                profileForm.elements.namedItem(key).value = value;
            }
        });

        function updateProfileHeader(data) {
            const first = String(data.firstName || "").trim();
            const last = String(data.lastName || "").trim();
            const full = `${first} ${last}`.trim() || "Lumear Buyer";
            $("#lacSidebarName").textContent = full;
            $("#lacAvatar").textContent = (
                (first.charAt(0) + last.charAt(0)) || "LB"
            ).toUpperCase();
        }

        updateProfileHeader(profile);

        profileForm.addEventListener("submit", event => {
            event.preventDefault();

            if (!profileForm.reportValidity()) return;

            const formData = new FormData(profileForm);
            const data = Object.fromEntries(formData.entries());

            if (write(PROFILE_KEY, data)) {
                updateProfileHeader(data);
                toast("Profile changes saved in this browser.");
            }
        });

        /* ADDRESSES */

        const addressForm = $("#lacAddressForm");
        let addresses = read(ADDRESS_KEY, []);
        if (!Array.isArray(addresses)) addresses = [];

        function renderAddresses() {
            const list = $("#lacAddressList");

            if (!addresses.length) {
                list.innerHTML = '<div class="lac-address-empty">No saved addresses yet. Add your first delivery address below.</div>';
                return;
            }

            list.innerHTML = addresses.map(address => `
                <div class="lac-address-card">
                    <span>⌖</span>
                    <div>
                        <strong>${escapeHTML(address.recipient)}</strong>
                        ${address.isDefault ? '<small> · DEFAULT ADDRESS</small>' : ""}
                        <p>
                            ${escapeHTML(address.street)},
                            ${escapeHTML(address.barangay)},
                            ${escapeHTML(address.city)},
                            ${escapeHTML(address.province)}
                            ${escapeHTML(address.postal)}
                            <br>${escapeHTML(address.mobile)}
                        </p>
                        <div class="lac-address-actions">
                            <button type="button" data-address-action="edit" data-id="${escapeHTML(address.id)}">Edit</button>
                            ${!address.isDefault ? `<button type="button" data-address-action="default" data-id="${escapeHTML(address.id)}">Set Default</button>` : ""}
                            <button type="button" data-address-action="delete" data-id="${escapeHTML(address.id)}">Remove</button>
                        </div>
                    </div>
                </div>
            `).join("");
        }

        function resetAddressForm() {
            addressForm.reset();
            addressForm.elements.namedItem("addressId").value = "";
            $("#lacAddressFormTitle").textContent = "Add a New Address";
            $("#lacCancelAddress").hidden = true;
        }

        addressForm.addEventListener("submit", event => {
            event.preventDefault();
            if (!addressForm.reportValidity()) return;

            const data = Object.fromEntries(new FormData(addressForm).entries());
            const id = data.addressId || (
                window.crypto?.randomUUID?.() || "address-" + Date.now()
            );

            const isDefault = !!data.isDefault || addresses.length === 0;
            const next = {
                ...data,
                id,
                isDefault
            };

            if (isDefault) {
                addresses.forEach(item => item.isDefault = false);
            }

            const index = addresses.findIndex(item => item.id === id);

            if (index >= 0) {
                addresses[index] = next;
            } else {
                addresses.push(next);
            }

            if (write(ADDRESS_KEY, addresses)) {
                resetAddressForm();
                renderAddresses();
                toast("Delivery address saved.");
            }
        });

        $("#lacAddressList").addEventListener("click", event => {
            const button = event.target.closest("[data-address-action]");
            if (!button) return;

            const index = addresses.findIndex(item => item.id === button.dataset.id);
            if (index < 0) return;

            const action = button.dataset.addressAction;
            const address = addresses[index];

            if (action === "edit") {
                Object.entries(address).forEach(([key, value]) => {
                    const field = addressForm.elements.namedItem(key);
                    if (!field) return;
                    if (field.type === "checkbox") field.checked = !!value;
                    else field.value = value;
                });

                addressForm.elements.namedItem("addressId").value = address.id;
                $("#lacAddressFormTitle").textContent = "Edit Delivery Address";
                $("#lacCancelAddress").hidden = false;
                addressForm.scrollIntoView({ behavior: "smooth", block: "center" });
            }

            if (action === "default") {
                addresses.forEach(item => item.isDefault = item.id === address.id);
                if (write(ADDRESS_KEY, addresses)) {
                    renderAddresses();
                    toast("Default address updated.");
                }
            }

            if (action === "delete") {
                if (!confirm("Remove this saved address?")) return;

                const wasDefault = address.isDefault;
                addresses.splice(index, 1);

                if (wasDefault && addresses.length) addresses[0].isDefault = true;

                if (write(ADDRESS_KEY, addresses)) {
                    renderAddresses();
                    resetAddressForm();
                    toast("Address removed.");
                }
            }
        });

        $("#lacCancelAddress").addEventListener("click", resetAddressForm);
        renderAddresses();

        /* NOTIFICATION PREFERENCES */

        const prefsForm = $("#lacPreferencesForm");
        const preferences = read(PREF_KEY, {
            orders: true,
            promos: true,
            saved: true
        });

        ["orders", "promos", "saved"].forEach(name => {
            prefsForm.elements.namedItem(name).checked = preferences[name] !== false;
        });

        prefsForm.addEventListener("submit", event => {
            event.preventDefault();

            const next = {};
            ["orders", "promos", "saved"].forEach(name => {
                next[name] = prefsForm.elements.namedItem(name).checked;
            });

            if (write(PREF_KEY, next)) toast("Preferences saved.");
        });
    }

    /* =========================================
       SAVED PRODUCTS
       ========================================= */

    const savedPage = $("#lumearSaved");

    if (savedPage) {
        let saved = read(SAVED_KEY, []);
        if (!Array.isArray(saved)) saved = [];

        function renderSaved() {
            $("#lacSavedCount").textContent = saved.length;
            $("#lacSavedGrid").hidden = saved.length === 0;
            $("#lacSavedEmpty").hidden = saved.length !== 0;
            $("#lacClearSaved").disabled = saved.length === 0;

            $("#lacSavedGrid").innerHTML = saved.map(item => {
                const url = productLink(item.slug);
                const photo = imageURL(item.image);

                return `
                    <article class="lac-saved-card">
                        <div class="lac-saved-photo">
                            <a href="${url}">
                                ${photo
                                    ? `<img src="${escapeHTML(photo)}" alt="${escapeHTML(item.name)}" loading="lazy">`
                                    : `<div style="height:100%;display:grid;place-items:center;font-size:40px;color:#873632">✦</div>`
                                }
                            </a>
                            <button type="button" class="lac-saved-remove"
                                data-remove-saved="${escapeHTML(item.id)}"
                                aria-label="Remove ${escapeHTML(item.name)} from saved products">×</button>
                        </div>
                        <div class="lac-saved-body">
                            <span>SAVED DISCOVERY</span>
                            <a href="${url}">${escapeHTML(item.name)}</a>
                            <strong>${money(item.price)}</strong>
                            <a class="lac-saved-view" href="${url}">View Product ↗</a>
                        </div>
                    </article>
                `;
            }).join("");
        }

        $("#lacSavedGrid").addEventListener("click", event => {
            const button = event.target.closest("[data-remove-saved]");
            if (!button) return;

            saved = saved.filter(item => String(item.id) !== button.dataset.removeSaved);

            if (write(SAVED_KEY, saved)) {
                renderSaved();
                toast("Product removed from saved finds.");
            }
        });

        $("#lacClearSaved").addEventListener("click", () => {
            if (!saved.length || !confirm("Remove all saved products?")) return;

            if (write(SAVED_KEY, [])) {
                saved = [];
                renderSaved();
                toast("Saved products cleared.");
            }
        });

        renderSaved();
    }

    /* =========================================
       ORDER HISTORY
       ========================================= */

    const ordersPage = $("#lumearOrders");

    if (ordersPage) {
        let orders = read(ORDERS_KEY, []);
        if (!Array.isArray(orders)) orders = [];

        let activeStatus = "all";
        let searchTerm = "";

        const normalizedStatus = order =>
            String(order.status || "pending").toLowerCase();

        function renderOrders() {
            const filtered = orders.filter(order => {
                const status = normalizedStatus(order);
                const matchesStatus = activeStatus === "all" || status === activeStatus;
                const text = [
                    order.orderNumber,
                    order.id,
                    ...(Array.isArray(order.items) ? order.items.map(item => item.name) : [])
                ].join(" ").toLowerCase();

                return matchesStatus && text.includes(searchTerm);
            });

            $("#lacOrdersAll").textContent = orders.length;
            $("#lacOrdersShip").textContent =
                orders.filter(order => normalizedStatus(order) === "processing").length;
            $("#lacOrdersTransit").textContent =
                orders.filter(order => normalizedStatus(order) === "shipped").length;
            $("#lacOrdersDone").textContent =
                orders.filter(order => normalizedStatus(order) === "delivered").length;

            const list = $("#lacOrderList");
            const empty = $("#lacOrdersEmpty");

            empty.hidden = filtered.length !== 0;

            if (!filtered.length) {
                list.innerHTML = "";
                $("#lacOrderEmptyTitle").textContent =
                    orders.length ? "No matching orders." : "No orders yet.";
                $("#lacOrderEmptyText").textContent = orders.length
                    ? "Try another order status or search term."
                    : "Once checkout is connected, completed purchases will appear here.";
                return;
            }

            list.innerHTML = filtered.map((order, index) => {
                const items = Array.isArray(order.items) ? order.items : [];
                const status = normalizedStatus(order);
                const orderNumber = order.orderNumber || order.id || `ORDER-${index + 1}`;
                const total = Number(order.total) || 0;
                const date = order.createdAt
                    ? new Date(order.createdAt).toLocaleDateString("en-PH", {
                        year: "numeric", month: "short", day: "numeric"
                    })
                    : "Date unavailable";

                return `
                    <article class="lac-order-card">
                        <div class="lac-order-card-top">
                            <strong>${escapeHTML(orderNumber)} · ${escapeHTML(date)}</strong>
                            <span>${escapeHTML(status.toUpperCase())}</span>
                        </div>
                        <div class="lac-order-card-body">
                            ${items.map(item => {
                                const photo = imageURL(item.image);
                                return `
                                    <div class="lac-order-product">
                                        ${photo ? `<img src="${escapeHTML(photo)}" alt="${escapeHTML(item.name)}">` : ""}
                                        <div>
                                            <strong>${escapeHTML(item.name)}</strong>
                                            <p>Qty: ${Number(item.quantity) || 1} · ${money(item.price)}</p>
                                        </div>
                                    </div>
                                `;
                            }).join("")}
                        </div>
                        <div class="lac-order-card-bottom">
                            <div><span style="font-size:11px;color:#8b7b70">Order Total</span><br><strong>${money(total)}</strong></div>
                            <button type="button" data-order-details="${index}">View Details ↓</button>
                        </div>
                        <div class="lac-order-detail" hidden>
                            <p><strong>Status:</strong> ${escapeHTML(status)}</p>
                            <p><strong>Order Number:</strong> ${escapeHTML(orderNumber)}</p>
                            <p><strong>Items:</strong> ${items.length}</p>
                            <p>Tracking information will appear when logistics integration is connected.</p>
                        </div>
                    </article>
                `;
            }).join("");
        }

        $$("#lacOrderTabs button").forEach(button => {
            button.addEventListener("click", () => {
                activeStatus = button.dataset.status;
                $$("#lacOrderTabs button").forEach(tab =>
                    tab.classList.toggle("active", tab === button)
                );
                renderOrders();
            });
        });

        $("#lacOrderSearch").addEventListener("input", event => {
            searchTerm = event.target.value.trim().toLowerCase();
            renderOrders();
        });

        $("#lacOrderList").addEventListener("click", event => {
            const button = event.target.closest("[data-order-details]");
            if (!button) return;

            const details = button.closest(".lac-order-card").querySelector(".lac-order-detail");
            details.hidden = !details.hidden;
            button.textContent = details.hidden ? "View Details ↓" : "Hide Details ↑";
        });

        renderOrders();
    }
});
