/* =====================================================
   LUMEAR LOGISTICS — LIVE MESSAGES
   Laravel + MySQL, polling every 3 seconds
   ===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const app = document.getElementById("logisticsMessagesApp");
    if (!app) return;

    const $ = id => document.getElementById(id);
    const API = "/logistics/api/messages";

    let contacts = [];
    let conversations = [];
    let activeId = null;
    let currentFilter = "all";
    let toastTimer = null;
    let refreshing = false;
    let sending = false;
    let activeMessages = [];
    let lastMessageKey = "";
    let pollingTimer = null;

    function create(tag, className = "", value = "") {
        const element = document.createElement(tag);

        if (className) element.className = className;
        if (value !== "") element.textContent = String(value);

        return element;
    }

    function getContact(id) {
        return contacts.find(
            contact => Number(contact.id) === Number(id)
        );
    }

    function getConversation(id) {
        return conversations.find(
            chat => Number(chat.contactId) === Number(id)
        );
    }

    function initials(name) {
        const words = String(name || "")
            .trim()
            .split(/\s+/)
            .filter(Boolean);

        return words
            .slice(0, 2)
            .map(word => word[0].toUpperCase())
            .join("") || "--";
    }

    function parseDate(value) {
        if (!value) return new Date();

        // Laravel returns "YYYY-MM-DD HH:mm:ss" by default.
        const normalized = String(value).includes("T")
            ? String(value)
            : String(value).replace(" ", "T");

        const date = new Date(normalized);

        return Number.isNaN(date.getTime())
            ? new Date()
            : date;
    }

    function formatTime(value) {
        return parseDate(value).toLocaleTimeString("en-PH", {
            hour: "numeric",
            minute: "2-digit"
        });
    }

    function formatDay(value) {
        return parseDate(value).toLocaleDateString("en-PH", {
            month: "short",
            day: "numeric",
            year: "numeric"
        });
    }

    function toast(message) {
        const element = $("lmToast");
        if (!element) return;

        element.textContent = message;
        element.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            element.classList.remove("show");
        }, 3000);
    }

    async function request(path = "", options = {}) {
        const method = (options.method || "GET").toUpperCase();

        const headers = {
            Accept: "application/json",
            ...options.headers
        };

        if (method !== "GET") {
            const token = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

            if (!token) {
                throw new Error(
                    "Missing CSRF token in Logistics layout."
                );
            }

            headers["Content-Type"] = "application/json";
            headers["X-CSRF-TOKEN"] = token;
        }

        const response = await fetch(API + path, {
            ...options,
            method,
            credentials: "same-origin",
            headers
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errors = data.errors
                ? Object.values(data.errors).flat().join(" ")
                : null;

            throw new Error(
                errors ||
                data.message ||
                `Request failed (${response.status}).`
            );
        }

        return data;
    }

    function openModal(id) {
        const modal = $(id);
        if (!modal) return;

        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        modal.querySelector("button")?.focus();
    }

    function closeModals() {
        document.querySelectorAll(".lm-modal.open").forEach(modal => {
            modal.classList.remove("open");
            modal.setAttribute("aria-hidden", "true");
        });
    }

    document.querySelectorAll("[data-lm-close]").forEach(button => {
        button.addEventListener("click", closeModals);
    });

    document.querySelectorAll(".lm-modal").forEach(modal => {
        modal.addEventListener("click", event => {
            if (event.target === modal) closeModals();
        });
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") closeModals();
    });

    function latestMessage(chat) {
        return chat.latest || null;
    }

    function renderStats() {
        $("lmTotalConversations").textContent =
            conversations.length;

        $("lmUnreadCount").textContent =
            conversations.filter(chat => chat.unread > 0).length;

        $("lmRiderCount").textContent =
            conversations.filter(chat =>
                getContact(chat.contactId)?.role === "Rider"
            ).length;

        $("lmInboxCount").textContent = conversations.length;
    }

    function filteredConversations() {
        const query = $("lmSearch").value.trim().toLowerCase();

        return [...conversations]
            .filter(chat => {
                const contact = getContact(chat.contactId);

                if (!contact) return false;

                const searchable = [
                    contact.name,
                    contact.role,
                    contact.area,
                    contact.email,
                    contact.id
                ].join(" ").toLowerCase();

                const matchesSearch = searchable.includes(query);

                const matchesFilter =
                    currentFilter === "all" ||
                    (currentFilter === "unread" && chat.unread > 0) ||
                    contact.role === currentFilter;

                return matchesSearch && matchesFilter;
            })
            .sort((a, b) =>
                Number(b.latest?.id || 0) -
                Number(a.latest?.id || 0)
            );
    }

    function makeAvatar(contact) {
        return create(
            "div",
            `lm-avatar ${contact.role.toLowerCase()}`,
            initials(contact.name)
        );
    }

    function renderConversationList() {
        const list = $("lmConversationList");
        const filtered = filteredConversations();

        list.replaceChildren();
        $("lmListEmpty").hidden = filtered.length > 0;

        filtered.forEach(chat => {
            const contact = getContact(chat.contactId);
            if (!contact) return;

            const latest = latestMessage(chat);

            const button = create(
                "button",
                `lm-conversation ${
                    Number(activeId) === Number(chat.contactId)
                        ? "active"
                        : ""
                }`
            );

            button.type = "button";
            button.setAttribute(
                "aria-label",
                `Open conversation with ${contact.name}`
            );

            button.appendChild(makeAvatar(contact));

            const body = create("div", "lm-conversation-body");
            const top = create("div", "lm-conversation-top");

            top.appendChild(
                create("strong", "", contact.name)
            );

            top.appendChild(
                create(
                    "span",
                    "lm-conversation-time",
                    latest ? formatTime(latest.time) : ""
                )
            );

            body.appendChild(top);

            body.appendChild(
                create(
                    "span",
                    "lm-conversation-role",
                    contact.role
                )
            );

            const preview = create(
                "div",
                "lm-conversation-preview"
            );

            preview.appendChild(
                create(
                    "span",
                    "",
                    latest
                        ? `${
                            latest.sender === "me" ? "You: " : ""
                        }${latest.text}`
                        : "Start a conversation"
                )
            );

            if (chat.unread > 0) {
                preview.appendChild(
                    create(
                        "span",
                        "lm-unread-badge",
                        chat.unread
                    )
                );
            }

            body.appendChild(preview);
            button.appendChild(body);

            button.addEventListener("click", () => {
                selectConversation(chat.contactId, true);
            });

            list.appendChild(button);
        });
    }

    async function markAsRead(id, silent = false) {
        if (!id) return;

        try {
            await request(`/${id}/read`, {
                method: "POST"
            });

            const chat = getConversation(id);

            if (chat) chat.unread = 0;

            renderStats();
            renderConversationList();

            if (!silent) {
                toast("Conversation marked as read.");
            }
        } catch (error) {
            if (!silent) toast(error.message);
        }
    }

    async function selectConversation(
        id,
        openMobile = false
    ) {
        const contact = getContact(id);
        if (!contact) return;

        activeId = Number(id);
        activeMessages = [];
        lastMessageKey = "";

        renderConversationList();
        renderChat();

        if (openMobile) {
            document.querySelector(".lm-workspace")
                ?.classList.add("mobile-chat-open");
        }

        await refreshMessages(true);

        if (activeId === Number(id)) {
            await markAsRead(id, true);
        }
    }

    function renderChat() {
        const contact = getContact(activeId);
        const list = $("lmMessageList");

        list.replaceChildren();

        if (!contact) {
            $("lmChatName").textContent =
                "Select a conversation";

            $("lmChatRole").textContent =
                "No conversation selected";

            $("lmChatAvatar").textContent = "--";

            list.appendChild(
                create(
                    "div",
                    "lm-chat-placeholder",
                    "Choose a conversation to view messages."
                )
            );

            $("lmMessageInput").disabled = true;
            $("lmSendButton").disabled = true;

            return;
        }

        $("lmChatName").textContent = contact.name;

        $("lmChatRole").textContent =
            `${contact.role} · ${contact.area}`;

        const avatar = $("lmChatAvatar");

        avatar.textContent = initials(contact.name);
        avatar.className =
            `lm-avatar ${contact.role.toLowerCase()}`;

        $("lmMessageInput").disabled = false;
        $("lmSendButton").disabled = sending;

        let previousDay = "";

        activeMessages.forEach(message => {
            const day = formatDay(message.time);

            if (day !== previousDay) {
                list.appendChild(
                    create(
                        "div",
                        "lm-date-separator",
                        day
                    )
                );

                previousDay = day;
            }

            const row = create(
                "div",
                `lm-message-row ${
                    message.sender === "me"
                        ? "sent"
                        : "received"
                }`
            );

            const group = create(
                "div",
                "lm-message-group"
            );

            group.appendChild(
                create(
                    "div",
                    "lm-bubble",
                    message.text
                )
            );

            group.appendChild(
                create(
                    "span",
                    "lm-message-meta",
                    `${
                        formatTime(message.time)
                    }${
                        message.sender === "me"
                            ? message.read
                                ? " · Read"
                                : " · Sent"
                            : ""
                    }`
                )
            );

            row.appendChild(group);
            list.appendChild(row);
        });

        if (!activeMessages.length) {
            list.appendChild(
                create(
                    "div",
                    "lm-chat-placeholder",
                    "No messages yet. Start the conversation."
                )
            );
        }

        requestAnimationFrame(() => {
            list.scrollTop = list.scrollHeight;
        });
    }

    async function refreshMessages(force = false) {
        if (!activeId) return;

        const requestedId = activeId;

        try {
            const data = await request(`/${requestedId}`);

            if (activeId !== requestedId) return;

            const messages = Array.isArray(data.messages)
                ? data.messages
                : [];

            const key = messages.map(m =>
                `${m.id}:${m.read ? 1 : 0}`
            ).join("|");

            if (force || key !== lastMessageKey) {
                activeMessages = messages;
                lastMessageKey = key;
                renderChat();

                if (messages.some(m =>
                    m.sender === "them" && !m.read
                )) {
                    await markAsRead(requestedId, true);
                }
            }
        } catch (error) {
            if (force) toast(error.message);
        }
    }

    async function refreshInbox(silent = false) {
        if (refreshing) return;
        refreshing = true;

        try {
            const result = await request();

            contacts = Array.isArray(result.contacts)
                ? result.contacts
                : [];

            conversations = Array.isArray(result.conversations)
                ? result.conversations
                : [];

            if (
                activeId &&
                !getContact(activeId)
            ) {
                activeId = null;
                activeMessages = [];
                lastMessageKey = "";
                renderChat();
            }

            if (!activeId && conversations.length) {
                activeId = Number(
                    conversations[0].contactId
                );

                await refreshMessages(true);
            }

            renderStats();
            renderConversationList();
        } catch (error) {
            if (!silent) toast(error.message);
        } finally {
            refreshing = false;
        }
    }

    async function sendMessage(event) {
        event.preventDefault();

        if (!activeId || sending) return;

        const input = $("lmMessageInput");
        const text = input.value.trim();

        if (!text) return;

        if (text.length > 2000) {
            toast("Messages must be 2,000 characters or fewer.");
            return;
        }

        sending = true;
        $("lmSendButton").disabled = true;

        const recipientId = activeId;

        try {
            await request("", {
                method: "POST",
                body: JSON.stringify({
                    recipient_id: recipientId,
                    body: text
                })
            });

            input.value = "";
            input.style.height = "44px";

            await refreshMessages(true);
            await refreshInbox(true);

            input.focus();
        } catch (error) {
            toast(error.message);
        } finally {
            sending = false;
            $("lmSendButton").disabled = false;
        }
    }

    function renderContacts() {
        const list = $("lmContactList");
        list.replaceChildren();

        if (!contacts.length) {
            list.appendChild(
                create(
                    "p",
                    "lm-chat-placeholder",
                    "No connected riders or sellers found."
                )
            );
            return;
        }

        contacts.forEach(contact => {
            const button = create(
                "button",
                "lm-contact"
            );

            button.type = "button";
            button.appendChild(makeAvatar(contact));

            const details = create("div");

            details.appendChild(
                create("strong", "", contact.name)
            );

            details.appendChild(
                create(
                    "small",
                    "",
                    `${contact.role} · ${contact.area}`
                )
            );

            button.appendChild(details);

            button.addEventListener("click", async () => {
                closeModals();
                await selectConversation(contact.id, true);
            });

            list.appendChild(button);
        });
    }

    function showContactInfo() {
        const contact = getContact(activeId);

        if (!contact) {
            toast("Please select a conversation first.");
            return;
        }

        const grid = $("lmInfoGrid");
        grid.replaceChildren();

        [
            ["Contact Name", contact.name],
            ["Account Role", contact.role],
            ["User ID", contact.id],
            ["Email", contact.email],
            ["Assigned Area", contact.area]
        ].forEach(([label, value]) => {
            const item = create(
                "div",
                "lm-info-item"
            );

            item.appendChild(
                create("span", "", label)
            );

            item.appendChild(
                create("strong", "", value || "—")
            );

            grid.appendChild(item);
        });

        openModal("lmInfoModal");
    }

    // SEARCH

    $("lmSearch").addEventListener(
        "input",
        renderConversationList
    );

    // FILTERS

    document.querySelectorAll(
        "[data-lm-filter]"
    ).forEach(button => {
        button.addEventListener("click", () => {
            currentFilter = button.dataset.lmFilter;

            document.querySelectorAll(
                "[data-lm-filter]"
            ).forEach(item => {
                item.classList.remove("active");
            });

            button.classList.add("active");

            renderConversationList();
        });
    });

    // SEND MESSAGE

    $("lmMessageForm").addEventListener(
        "submit",
        sendMessage
    );

    $("lmMessageInput").addEventListener(
        "keydown",
        event => {
            if (
                event.key === "Enter" &&
                !event.shiftKey &&
                !event.isComposing
            ) {
                event.preventDefault();
                $("lmMessageForm").requestSubmit();
            }
        }
    );

    // AUTO RESIZE MESSAGE INPUT

    $("lmMessageInput").addEventListener(
        "input",
        event => {
            const textarea = event.target;

            textarea.style.height = "44px";
            textarea.style.height =
                `${Math.min(
                    textarea.scrollHeight,
                    145
                )}px`;
        }
    );

    // NEW MESSAGE

    $("lmNewMessage").addEventListener(
        "click",
        () => {
            renderContacts();
            openModal("lmNewModal");
        }
    );

    // MARK AS READ

    $("lmMarkRead").addEventListener(
        "click",
        async () => {
            if (activeId) {
                await markAsRead(activeId);
            }
        }
    );

    // CONTACT INFO

    $("lmChatInfo").addEventListener(
        "click",
        showContactInfo
    );

    // MOBILE BACK BUTTON

    $("lmBack").addEventListener(
        "click",
        () => {
            document.querySelector(".lm-workspace")
                ?.classList.remove("mobile-chat-open");
        }
    );

    // AUTOMATIC UPDATES

    async function poll() {
        if (document.hidden) return;

        await refreshInbox(true);
        await refreshMessages();
    }

    document.addEventListener(
        "visibilitychange",
        () => {
            if (!document.hidden) poll();
        }
    );

    window.addEventListener(
        "beforeunload",
        () => {
            clearInterval(pollingTimer);
        }
    );

    // INITIAL LOAD

    renderChat();
    refreshInbox();

    pollingTimer = setInterval(poll, 3000);
});
