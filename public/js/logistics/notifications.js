/* ====================================================
   LUMEAR LOGISTICS — LIVE NOTIFICATIONS
   MySQL-backed notification polling
   ==================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const app = document.getElementById("lnNotificationsApp");
    if (!app) return;

    const list = document.getElementById("lnNotificationList");
    const badge = document.getElementById("lnBellBadge");
    const label = document.getElementById("lnUnreadLabel");
    const markAll = document.getElementById("lnMarkAllRead");
    const errorBox = document.getElementById("lnNotificationError");

    const baseUrl = "/logistics/api/notifications";

    let loading = false;
    let modifying = false;

    const csrf = document.querySelector(
        'meta[name="csrf-token"]'
    )?.content;

    function showError(message = "") {
        errorBox.hidden = !message;
        errorBox.textContent = message;
    }

    function formatTime(value) {
        if (!value) return "";

        const date = new Date(
            String(value).replace(" ", "T") +
            (/Z$|[+-]\d\d:\d\d$/.test(String(value))
                ? ""
                : "")
        );

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleString("en-PH", {
            dateStyle: "medium",
            timeStyle: "short"
        });
    }

    async function request(url, method = "GET") {
        const response = await fetch(url, {
            method,
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                ...(csrf ? { "X-CSRF-TOKEN": csrf } : {})
            }
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                data.message ||
                `Notification request failed (${response.status}).`
            );
        }

        return data;
    }

    function makeElement(tag, className, text) {
        const element = document.createElement(tag);

        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;

        return element;
    }

    function safeLocalUrl(value) {
        if (!value || typeof value !== "string") {
            return null;
        }

        try {
            const url = new URL(value, window.location.origin);

            if (
                url.origin !== window.location.origin ||
                !url.pathname.startsWith("/logistics/")
            ) {
                return null;
            }

            return url.pathname + url.search + url.hash;
        } catch {
            return null;
        }
    }

    function render(data) {
        const unread = Number(data.unread_count || 0);
        const items = Array.isArray(data.notifications)
            ? data.notifications
            : [];

        label.textContent = unread === 0
            ? "No new alerts"
            : `${unread} unread`;

        if (badge) {
            badge.hidden = unread === 0;
            badge.textContent = unread > 99 ? "99+" : String(unread);
        }

        markAll.hidden = unread === 0;
        list.replaceChildren();

        if (!items.length) {
            const empty = makeElement(
                "div",
                "ln-empty",
                "No notifications yet. New Logistics activity will appear here."
            );

            list.appendChild(empty);
            return;
        }

        items.forEach(notification => {
            const unreadItem = !notification.read_at;

            const item = makeElement(
                "div",
                "ln-item" + (unreadItem ? " ln-unread" : "")
            );

            const body = makeElement("div", "ln-item-body");

            const title = makeElement(
                "strong",
                "ln-item-title",
                notification.title || "Notification"
            );

            const message = makeElement(
                "p",
                "ln-item-message",
                notification.message || ""
            );

            const time = makeElement(
                "span",
                "ln-item-time",
                formatTime(notification.created_at)
            );

            body.append(title, message, time);

            const actions = makeElement(
                "div",
                "ln-item-actions"
            );

            const destination = safeLocalUrl(notification.url);

            if (destination) {
                const open = makeElement(
                    "button",
                    "ln-item-button",
                    "View"
                );

                open.type = "button";

                open.addEventListener("click", async () => {
                    try {
                        if (unreadItem) {
                            await markRead(notification.id);
                        }

                        window.location.assign(destination);
                    } catch (error) {
                        showError(error.message);
                    }
                });

                actions.appendChild(open);
            }

            if (unreadItem) {
                const read = makeElement(
                    "button",
                    "ln-item-button",
                    "Mark read"
                );

                read.type = "button";

                read.addEventListener("click", () => {
                    markRead(notification.id).catch(error => {
                        showError(error.message);
                    });
                });

                actions.appendChild(read);
            }

            item.append(body, actions);
            list.appendChild(item);
        });
    }

    async function loadNotifications() {
        if (loading || modifying) return;

        loading = true;

        try {
            const data = await request(baseUrl);
            render(data);
            showError("");
        } catch (error) {
            showError(error.message);
        } finally {
            loading = false;
        }
    }

    async function markRead(id) {
        if (modifying) return;

        modifying = true;

        try {
            await request(
                `${baseUrl}/${encodeURIComponent(id)}/read`,
                "POST"
            );
        } finally {
            modifying = false;
        }

        await loadNotifications();
    }

    markAll.addEventListener("click", async () => {
        if (modifying) return;

        modifying = true;
        markAll.disabled = true;

        try {
            await request(`${baseUrl}/read-all`, "POST");
            showError("");
        } catch (error) {
            showError(error.message);
        } finally {
            modifying = false;
            markAll.disabled = false;
        }

        await loadNotifications();
    });

    loadNotifications();

    // Refresh every 15 seconds while the page is visible.
    const poller = setInterval(() => {
        if (!document.hidden) {
            loadNotifications();
        }
    }, 15000);

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            loadNotifications();
        }
    });

    window.addEventListener("pagehide", () => {
        clearInterval(poller);
    });
});
