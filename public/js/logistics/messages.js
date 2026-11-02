
/* =====================================================
   LUMEAR LOGISTICS — MESSAGES
   Frontend demo with browser localStorage
   ===================================================== */

document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const app = document.getElementById("logisticsMessagesApp");
    if (!app) return;

    const $ = id => document.getElementById(id);

    const STORAGE_KEY = "lumear_logistics_messages_demo_v1";

    const contacts = [
        {
            id: "RDR-001",
            name: "Carlos Mendoza",
            role: "Rider",
            area: "Santa Cruz, Laguna"
        },
        {
            id: "RDR-002",
            name: "Joshua Reyes",
            role: "Rider",
            area: "Victoria, Laguna"
        },
        {
            id: "RDR-003",
            name: "Mark Villanueva",
            role: "Rider",
            area: "Pila, Laguna"
        },
        {
            id: "SEL-001",
            name: "Luna Essentials",
            role: "Seller",
            area: "Santa Cruz, Laguna"
        },
        {
            id: "SEL-002",
            name: "Galleria Crafts",
            role: "Seller",
            area: "Victoria, Laguna"
        },
        {
            id: "ADM-001",
            name: "LUMEAR Administrator",
            role: "Admin",
            area: "LUMEAR Marketplace"
        }
    ];

    const sampleConversations = [
        {
            contactId: "RDR-001",
            unread: 2,
            messages: [
                {
                    id: "m1",
                    sender: "them",
                    text: "Good morning! I have received the assigned parcels.",
                    time: "2026-10-25T08:30:00"
                },
                {
                    id: "m2",
                    sender: "me",
                    text: "Good morning, Carlos. Please confirm once you start the delivery route.",
                    time: "2026-10-25T08:32:00"
                },
                {
                    id: "m3",
                    sender: "them",
                    text: "Understood. I will update you shortly.",
                    time: "2026-10-25T08:35:00"
                }
            ]
        },
        {
            contactId: "SEL-001",
            unread: 1,
            messages: [
                {
                    id: "m4",
                    sender: "them",
                    text: "Hello! May we confirm the pickup schedule for our parcels?",
                    time: "2026-10-25T09:05:00"
                }
            ]
        },
        {
            contactId: "ADM-001",
            unread: 0,
            messages: [
                {
                    id: "m5",
                    sender: "them",
                    text: "Please review the pending rider applications.",
                    time: "2026-10-24T14:10:00"
                },
                {
                    id: "m6",
                    sender: "me",
                    text: "Noted. We will review the applications.",
                    time: "2026-10-24T14:15:00"
                }
            ]
        },
        {
            contactId: "RDR-002",
            unread: 0,
            messages: [
                {
                    id: "m7",
                    sender: "me",
                    text: "Please check the parcels assigned for Victoria.",
                    time: "2026-10-24T10:20:00"
                },
                {
                    id: "m8",
                    sender: "them",
                    text: "All parcels have been checked.",
                    time: "2026-10-24T10:25:00"
                }
            ]
        }
    ];

    function loadConversations() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);

            if (stored) {
                const parsed = JSON.parse(stored);

                if (Array.isArray(parsed)) {
                    return parsed;
                }
            }
        } catch (error) {
            console.warn("Unable to load saved messages.", error);
        }

        return structuredClone(sampleConversations);
    }

    let conversations = loadConversations();
    let activeId = conversations[0]?.contactId || null;
    let currentFilter = "all";
    let toastTimer = null;

    function save() {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(conversations)
            );
            return true;
        } catch (error) {
            toast("Unable to save messages in this browser.");
            return false;
        }
    }

    function create(tag, className = "", value = "") {
        const element = document.createElement(tag);

        if (className) element.className = className;
        if (value !== "") element.textContent = String(value);

        return element;
    }

    function getContact(id) {
        return contacts.find(contact => contact.id === id);
    }

    function getConversation(id) {
        return conversations.find(chat => chat.contactId === id);
    }

    function initials(name) {
        return name.split(/\s+/)
            .slice(0, 2)
            .map(word => word[0].toUpperCase())
            .join("");
    }

    function formatTime(value) {
        return new Date(value).toLocaleTimeString("en-PH", {
            hour: "numeric",
            minute: "2-digit"
        });
    }

    function formatDay(value) {
        return new Date(value).toLocaleDateString("en-PH", {
            month: "short",
            day: "numeric",
            year: "numeric"
        });
    }

    function latestMessage(chat) {
        return chat.messages[chat.messages.length - 1] || null;
    }

    function toast(message) {
        const element = $("lmToast");

        element.textContent = message;
        element.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            element.classList.remove("show");
        }, 3000);
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

    function renderStats() {
        $("lmTotalConversations").textContent = conversations.length;

        $("lmUnreadCount").textContent = conversations.filter(
            chat => chat.unread > 0
        ).length;

        $("lmRiderCount").textContent = conversations.filter(
            chat => getContact(chat.contactId)?.role === "Rider"
        ).length;

        $("lmInboxCount").textContent = conversations.length;
    }

    function filteredConversations() {
        const query = $("lmSearch").value.trim().toLowerCase();

        return [...conversations]
            .filter(chat => {
                const contact = getContact(chat.contactId);

                if (!contact) return false;

                const searchText = [
                    contact.name,
                    contact.role,
                    contact.area,
                    contact.id
                ].join(" ").toLowerCase();

                const matchesSearch = searchText.includes(query);

                const matchesFilter =
                    currentFilter === "all" ||
                    (currentFilter === "unread" && chat.unread > 0) ||
                    contact.role === currentFilter;

                return matchesSearch && matchesFilter;
            })
            .sort((a, b) => {
                const aTime = latestMessage(a)?.time || "";
                const bTime = latestMessage(b)?.time || "";

                return bTime.localeCompare(aTime);
            });
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
            const latest = latestMessage(chat);

            const button = create(
                "button",
                `lm-conversation ${activeId === chat.contactId ? "active" : ""}`
            );

            button.type = "button";
            button.setAttribute(
                "aria-label",
                `Open conversation with ${contact.name}`
            );

            button.appendChild(makeAvatar(contact));

            const body = create("div", "lm-conversation-body");
            const top = create("div", "lm-conversation-top");

            top.appendChild(create("strong", "", contact.name));

            top.appendChild(
                create(
                    "span",
                    "lm-conversation-time",
                    latest ? formatTime(latest.time) : ""
                )
            );

            body.appendChild(top);
            body.appendChild(
                create("span", "lm-conversation-role", contact.role)
            );

            const preview = create("div", "lm-conversation-preview");

            preview.appendChild(
                create(
                    "span",
                    "",
                    latest
                        ? `${latest.sender === "me" ? "You: " : ""}${latest.text}`
                        : "Start a conversation"
                )
            );

            if (chat.unread > 0) {
                preview.appendChild(
                    create("span", "lm-unread-badge", chat.unread)
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

    function markAsRead(id) {
        const chat = getConversation(id);

        if (!chat || chat.unread === 0) return;

        chat.unread = 0;
        save();
        renderStats();
        renderConversationList();
    }

    function selectConversation(id, openMobile = false) {
        const chat = getConversation(id);
        const contact = getContact(id);

        if (!chat || !contact) return;

        activeId = id;

        markAsRead(id);
        renderConversationList();
        renderChat();

        if (openMobile) {
            document.querySelector(".lm-workspace")
                .classList.add("mobile-chat-open");
        }
    }

    function renderChat() {
        const contact = getContact(activeId);
        const chat = getConversation(activeId);
        const list = $("lmMessageList");

        list.replaceChildren();

        if (!contact || !chat) {
            $("lmChatName").textContent = "Select a conversation";
            $("lmChatRole").textContent = "No conversation selected";
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
        $("lmSendButton").disabled = false;

        let previousDay = "";

        chat.messages.forEach(message => {
            const day = formatDay(message.time);

            if (day !== previousDay) {
                list.appendChild(
                    create("div", "lm-date-separator", day)
                );
                previousDay = day;
            }

            const row = create(
                "div",
                `lm-message-row ${message.sender === "me" ? "sent" : "received"}`
            );

            const group = create("div", "lm-message-group");

            group.appendChild(
                create("div", "lm-bubble", message.text)
            );

            group.appendChild(
                create(
                    "span",
                    "lm-message-meta",
                    `${formatTime(message.time)}${message.sender === "me" ? " · Sent (demo)" : ""}`
                )
            );

            row.appendChild(group);
            list.appendChild(row);
        });

        if (!chat.messages.length) {
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

    function sendMessage(event) {
        event.preventDefault();

        const chat = getConversation(activeId);
        const input = $("lmMessageInput");

        if (!chat) return;

        const text = input.value.trim();

        if (!text) return;

        if (text.length > 2000) {
            toast("Messages must be 2,000 characters or fewer.");
            return;
        }

        chat.messages.push({
            id: `msg-${Date.now()}-${Math.random().toString(36).slice(2)}`,
            sender: "me",
            text,
            time: new Date().toISOString()
        });

        if (!save()) {
            chat.messages.pop();
            return;
        }

        input.value = "";
        input.style.height = "44px";

        renderStats();
        renderConversationList();
        renderChat();
        input.focus();
    }

    function renderContacts() {
        const list = $("lmContactList");
        list.replaceChildren();

        contacts.forEach(contact => {
            const button = create("button", "lm-contact");
            button.type = "button";

            button.appendChild(makeAvatar(contact));

            const details = create("div");
            details.appendChild(create("strong", "", contact.name));
            details.appendChild(
                create(
                    "small",
                    "",
                    `${contact.role} · ${contact.area}`
                )
            );

            button.appendChild(details);

            button.addEventListener("click", () => {
                let chat = getConversation(contact.id);

                if (!chat) {
                    chat = {
                        contactId: contact.id,
                        unread: 0,
                        messages: []
                    };

                    conversations.push(chat);

                    if (!save()) {
                        conversations.pop();
                        return;
                    }
                }

                closeModals();
                renderStats();
                selectConversation(contact.id, true);
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
            ["Account ID", contact.id],
            ["Assigned Area", contact.area]
        ].forEach(([label, value]) => {
            const item = create("div", "lm-info-item");

            item.appendChild(create("span", "", label));
            item.appendChild(create("strong", "", value));

            grid.appendChild(item);
        });

        openModal("lmInfoModal");
    }

    // EVENTS

    $("lmSearch").addEventListener("input", renderConversationList);

    document.querySelectorAll("[data-lm-filter]").forEach(button => {
        button.addEventListener("click", () => {
            currentFilter = button.dataset.lmFilter;

            document.querySelectorAll("[data-lm-filter]")
                .forEach(item => item.classList.remove("active"));

            button.classList.add("active");

            renderConversationList();
        });
    });

    $("lmMessageForm").addEventListener("submit", sendMessage);

    $("lmMessageInput").addEventListener("keydown", event => {
        if (event.key === "Enter" && !event.shiftKey &&
            !event.isComposing) {
            event.preventDefault();
            $("lmMessageForm").requestSubmit();
        }
    });

    $("lmMessageInput").addEventListener("input", event => {
        const textarea = event.target;
        textarea.style.height = "44px";
        textarea.style.height =
            `${Math.min(textarea.scrollHeight, 145)}px`;
    });

    $("lmNewMessage").addEventListener("click", () => {
        renderContacts();
        openModal("lmNewModal");
    });

    $("lmMarkRead").addEventListener("click", () => {
        if (!activeId) return;

        markAsRead(activeId);
        toast("Conversation marked as read.");
    });

    $("lmChatInfo").addEventListener("click", showContactInfo);

    $("lmBack").addEventListener("click", () => {
        document.querySelector(".lm-workspace")
            .classList.remove("mobile-chat-open");
    });

    window.addEventListener("storage", event => {
        if (event.key !== STORAGE_KEY) return;

        conversations = loadConversations();

        if (!getConversation(activeId)) {
            activeId = conversations[0]?.contactId || null;
        }

        renderStats();
        renderConversationList();
        renderChat();
    });

    renderStats();
    renderConversationList();
    renderChat();
});
