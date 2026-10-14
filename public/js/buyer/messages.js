
document.addEventListener("DOMContentLoaded", () => {
    const root = document.getElementById("lumearMessages");
    if (!root) return;

    const $ = selector => root.querySelector(selector);
    const $$ = selector => [...root.querySelectorAll(selector)];

    const STORAGE_KEY = "lumear_buyer_messages_demo_v1";

    const demoConversations = [
        {
            id: "terra-twine",
            seller: "Terra & Twine Home",
            initials: "T",
            subtitle: "Home & Living",
            unread: 2,
            messages: [
                {
                    id: 1,
                    sender: "seller",
                    text: "Hello! Thank you for checking out our handmade collection. Is there anything we can help you with?",
                    time: "10:02 AM"
                },
                {
                    id: 2,
                    sender: "buyer",
                    text: "Hello, just wanted to check if it comes with a cushion cover?",
                    time: "10:05 AM"
                },
                {
                    id: 3,
                    sender: "seller",
                    text: "Yes, it does! The cushion cover is machine-washable too.",
                    time: "10:06 AM"
                },
                {
                    id: 4,
                    sender: "seller",
                    text: "Your order has been packed and is being prepared for shipping.",
                    time: "10:07 AM"
                }
            ]
        },
        {
            id: "northline",
            seller: "Northline Electronics",
            initials: "N",
            subtitle: "Electronics & Gadgets",
            unread: 0,
            messages: [
                {
                    id: 1,
                    sender: "buyer",
                    text: "Hi! Does this pair of earbuds include a charging case?",
                    time: "Yesterday"
                },
                {
                    id: 2,
                    sender: "seller",
                    text: "Yes! The charging case is included with every purchase.",
                    time: "Yesterday"
                }
            ]
        },
        {
            id: "bloom-basil",
            seller: "Bloom & Basil",
            initials: "B",
            subtitle: "Beauty & Lifestyle",
            unread: 1,
            messages: [
                {
                    id: 1,
                    sender: "buyer",
                    text: "Is this available in a larger size?",
                    time: "Monday"
                },
                {
                    id: 2,
                    sender: "seller",
                    text: "Hi! We currently have two sizes available. You can check the options on the product page.",
                    time: "Monday"
                }
            ]
        },
        {
            id: "cornerstone",
            seller: "Cornerstone Books",
            initials: "C",
            subtitle: "Books & Stationery",
            unread: 0,
            messages: [
                {
                    id: 1,
                    sender: "seller",
                    text: "Thank you for supporting our bookstore. We hope you enjoy your new reads!",
                    time: "Last week"
                }
            ]
        }
    ];

    let conversations = [];
    let activeId = null;
    let filter = "all";
    let search = "";
    let toastTimer;

    function escapeHTML(value) {
        return String(value ?? "").replace(/[&<>"']/g, char => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        }[char]));
    }

    function load() {
        try {
            const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));

            if (Array.isArray(stored)) {
                return stored;
            }
        } catch (error) {
            console.warn("Could not read stored conversations.", error);
        }

        return structuredClone(demoConversations);
    }

    function save() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(conversations));
        } catch (error) {
            showToast("Unable to save messages in this browser.");
        }
    }

    function showToast(message) {
        const toast = $("#lmcToast");

        clearTimeout(toastTimer);

        toast.textContent = message;
        toast.classList.add("visible");

        toastTimer = setTimeout(() => {
            toast.classList.remove("visible");
        }, 2700);
    }

    function activeConversation() {
        return conversations.find(item => item.id === activeId);
    }

    function getLastMessage(conversation) {
        return conversation.messages[conversation.messages.length - 1];
    }

    function getUnreadCount() {
        return conversations.reduce(
            (total, item) => total + Number(item.unread || 0),
            0
        );
    }

    /* ==========================================
       RENDER CONVERSATIONS
       ========================================== */

    function renderConversations() {
        const list = $("#lmcConversationList");

        const filtered = conversations.filter(conversation => {
            const matchesSearch = conversation.seller
                .toLowerCase()
                .includes(search);

            const matchesFilter = filter === "all" ||
                Number(conversation.unread) > 0;

            return matchesSearch && matchesFilter;
        });

        $("#lmcConversationCount").textContent =
            conversations.length;

        $("#lmcUnreadCount").textContent =
            getUnreadCount();

        if (!filtered.length) {
            list.innerHTML = `
                <div style="padding:30px 20px;text-align:center;color:#9a8476;font-size:12px;line-height:1.8">
                    No conversations found.
                </div>
            `;
            return;
        }

        list.innerHTML = filtered.map(conversation => {
            const last = getLastMessage(conversation);

            return `
                <button
                    type="button"
                    class="lmc-conversation ${conversation.id === activeId ? "active" : ""}"
                    data-conversation="${escapeHTML(conversation.id)}"
                >
                    <div class="lmc-conversation-avatar">
                        ${escapeHTML(conversation.initials)}
                    </div>

                    <div class="lmc-conversation-info">

                        <div class="lmc-conversation-info-top">
                            <strong>${escapeHTML(conversation.seller)}</strong>
                            <span class="lmc-conversation-time">
                                ${escapeHTML(last?.time || "")}
                            </span>
                        </div>

                        <div class="lmc-conversation-preview">
                            <p>${escapeHTML(last?.text || "Start a conversation")}</p>

                            ${
                                Number(conversation.unread) > 0
                                    ? `<span class="lmc-unread-badge">${conversation.unread}</span>`
                                    : ""
                            }
                        </div>

                    </div>
                </button>
            `;
        }).join("");
    }

    /* ==========================================
       RENDER ACTIVE CHAT
       ========================================== */

    function renderChat() {
        const conversation = activeConversation();
        if (!conversation) return;

        $("#lmcChatName").textContent = conversation.seller;
        $("#lmcChatStatus").textContent =
            conversation.subtitle + " · Demo conversation";
        $("#lmcChatAvatar").textContent = conversation.initials;

        const list = $("#lmcMessagesList");

        list.innerHTML = `
            <div class="lmc-chat-intro">
                <div class="lmc-chat-intro-avatar">
                    ${escapeHTML(conversation.initials)}
                </div>
                <h4>${escapeHTML(conversation.seller)}</h4>
                <p>${escapeHTML(conversation.subtitle)} · Lumear Seller</p>
            </div>

            <div class="lmc-chat-date">
                <span>Conversation</span>
            </div>

            ${conversation.messages.map(message => `
                <div class="lmc-message ${message.sender === "buyer" ? "sent" : "received"}">

                    <div class="lmc-message-bubble">${escapeHTML(message.text)}</div>

    
                    <div class="lmc-message-meta">
                        <span>${escapeHTML(message.time)}</span>
                        ${message.sender === "buyer" ? '<span class="lmc-check">✓</span>' : ""}
                    </div>

                </div>
            `).join("")}
        `;

        const body = $("#lmcChatBody");
        body.scrollTop = body.scrollHeight;
    }

    /* ==========================================
       SELECT CONVERSATION
       ========================================== */

    function selectConversation(id) {
        const conversation = conversations.find(item => item.id === id);

        if (!conversation) return;

        activeId = id;
        conversation.unread = 0;

        save();
        renderConversations();
        renderChat();

        $(".lmc-layout").classList.add("mobile-chat-open");
    }

    $("#lmcConversationList").addEventListener("click", event => {
        const button = event.target.closest("[data-conversation]");

        if (button) {
            selectConversation(button.dataset.conversation);
        }
    });

    $("#lmcBackButton").addEventListener("click", () => {
        $(".lmc-layout").classList.remove("mobile-chat-open");
    });

    /* ==========================================
       SEARCH & FILTERS
       ========================================== */

    $("#lmcSearch").addEventListener("input", event => {
        search = event.target.value.trim().toLowerCase();
        renderConversations();
    });

    $$("[data-filter]").forEach(button => {
        button.addEventListener("click", () => {
            filter = button.dataset.filter;

            $$("[data-filter]").forEach(item => {
                item.classList.toggle("active", item === button);
            });

            renderConversations();
        });
    });

    /* ==========================================
       SEND MESSAGE
       ========================================== */

    const messageInput = $("#lmcMessageInput");

    function updateCharacterCount() {
        $("#lmcCharacterCount").textContent =
            `${messageInput.value.length} / 2000`;

        messageInput.style.height = "auto";
        messageInput.style.height =
            Math.min(messageInput.scrollHeight, 110) + "px";
    }

    messageInput.addEventListener("input", updateCharacterCount);

    messageInput.addEventListener("keydown", event => {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            $("#lmcMessageForm").requestSubmit();
        }
    });

    $("#lmcMessageForm").addEventListener("submit", event => {
        event.preventDefault();

        const conversation = activeConversation();
        const text = messageInput.value.trim();

        if (!conversation) {
            showToast("Select a conversation first.");
            return;
        }

        if (!text) return;

        const time = new Date().toLocaleTimeString("en-PH", {
            hour: "numeric",
            minute: "2-digit"
        });

        conversation.messages.push({
            id: Date.now(),
            sender: "buyer",
            text,
            time
        });

        messageInput.value = "";
        updateCharacterCount();

        save();
        renderChat();
        renderConversations();
    });

    /* ==========================================
       QUICK REPLIES
       ========================================== */

    $$("[data-reply]").forEach(button => {
        button.addEventListener("click", () => {
            messageInput.value = button.dataset.reply;
            updateCharacterCount();
            messageInput.focus();
        });
    });

    /* ==========================================
       OTHER BUTTONS
       ========================================== */

    $("#lmcNewConversation").addEventListener("click", () => {
        showToast(
            "New seller conversations will be available when seller messaging is connected."
        );
    });

    $("#lmcShopDetails").addEventListener("click", () => {
        const conversation = activeConversation();

        if (!conversation) return;

        showToast(
            `${conversation.seller} · ${conversation.subtitle}`
        );
    });

    $("#lmcChatOptions").addEventListener("click", () => {
        showToast(
            "Conversation settings will be available with account integration."
        );
    });

    /* ==========================================
       INITIALIZATION
       ========================================== */

    conversations = load();

    if (conversations.length) {
        activeId = conversations[0].id;
        conversations[0].unread = 0;
    }

    save();
    renderConversations();
    renderChat();
    updateCharacterCount();
});
