
@extends('layouts.buyer')

@section('title', 'Messages — Lumear')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/buyer/messages.css') }}?v=4">
@endpush

@section('content')

<div class="lmc-page" id="lumearMessages">

    <div class="lmc-container">

        {{-- BREADCRUMB --}}
        <nav class="lmc-breadcrumb">
            <a href="{{ url('/home') }}">Home</a>
            <span>/</span>
            <span>Messages</span>
        </nav>

        {{-- PAGE HEADING --}}
        <div class="lmc-page-heading">
            <div>
                <span class="lmc-eyebrow">YOUR CONVERSATIONS</span>
                <h1>Let's <em>connect.</em></h1>
                <p>
                    Questions about your finds? Your conversations
                    are all in one place.
                </p>
            </div>

            <div class="lmc-page-decoration">
                <span>✦</span>
                <span>GOOD CONVERSATIONS START HERE</span>
            </div>
        </div>

        {{-- MAIN MESSAGING INTERFACE --}}
        <div class="lmc-layout">

            {{-- LEFT SIDEBAR --}}
            <aside class="lmc-sidebar" id="lmcSidebar">

                <div class="lmc-sidebar-heading">
                    <div>
                        <span>INBOX</span>
                        <h2>Messages <small id="lmcConversationCount">0</small></h2>
                    </div>

                    <button
                        type="button"
                        class="lmc-icon-button"
                        id="lmcNewConversation"
                        title="Start a conversation"
                        aria-label="Start a conversation"
                    >
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L10 16l-4 1 1-4 9.5-9.5Z"/>
                        </svg>
                    </button>
                </div>

                {{-- SEARCH --}}
                <div class="lmc-sidebar-search">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round">
                        <circle cx="10.8" cy="10.8" r="6.5"/>
                        <path d="m16 16 4.5 4.5"/>
                    </svg>

                    <input
                        type="search"
                        id="lmcSearch"
                        placeholder="Search conversations..."
                        autocomplete="off"
                    >
                </div>

                {{-- FILTERS --}}
                <div class="lmc-sidebar-filters">
                    <button type="button" class="active" data-filter="all">
                        All Messages
                    </button>

                    <button type="button" data-filter="unread">
                        Unread
                        <span id="lmcUnreadCount">0</span>
                    </button>
                </div>

                {{-- CONVERSATION LIST --}}
                <div class="lmc-conversations" id="lmcConversationList"></div>

                <div class="lmc-sidebar-footer">
                    <span>✧</span>
                    <p>Stay connected with the shops you love.</p>
                </div>

            </aside>

            {{-- RIGHT CHAT PANEL --}}
            <section class="lmc-chat-panel" id="lmcChatPanel">

                {{-- CHAT HEADER --}}
                <div class="lmc-chat-header">

                    <button
                        type="button"
                        class="lmc-back-button"
                        id="lmcBackButton"
                        aria-label="Back to conversations"
                    >
                        ←
                    </button>

                    <div class="lmc-chat-avatar" id="lmcChatAvatar">T</div>

                    <div class="lmc-chat-header-info">
                        <h3 id="lmcChatName">Select a conversation</h3>
                        <p id="lmcChatStatus">Your messages will appear here.</p>
                    </div>

                    <div class="lmc-chat-header-actions">
                        <button
                            type="button"
                            id="lmcShopDetails"
                            class="lmc-header-shop-button"
                        >
                            Shop Info
                            <span>↗</span>
                        </button>

                        <button
                            type="button"
                            class="lmc-icon-button"
                            id="lmcChatOptions"
                            aria-label="Conversation information"
                            title="Conversation information"
                        >
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 11v5M12 8h.01"/>
                            </svg>
                        </button>
                    </div>

                </div>

                {{-- CHAT MESSAGES --}}
                <div class="lmc-chat-body" id="lmcChatBody">
                    <div class="lmc-chat-messages" id="lmcMessagesList"></div>
                </div>

                {{-- QUICK REPLIES --}}
                <div class="lmc-quick-replies" id="lmcQuickReplies">
                    <span>QUICK REPLIES</span>

                    <div class="lmc-quick-reply-list">
                        <button type="button" data-reply="Is this item still available?">
                            Product availability
                        </button>

                        <button type="button" data-reply="May I ask for an update on my order?">
                            Order update
                        </button>

                        <button type="button" data-reply="How long does shipping usually take?">
                            Shipping inquiry
                        </button>
                    </div>
                </div>

                {{-- MESSAGE COMPOSER --}}
                <form class="lmc-composer" id="lmcMessageForm">

                    <div class="lmc-composer-input-wrap">

                        <textarea
                            id="lmcMessageInput"
                            rows="1"
                            maxlength="2000"
                            placeholder="Write your message..."
                            aria-label="Write your message"
                        ></textarea>

                        <div class="lmc-composer-tools">
                            <span id="lmcCharacterCount">0 / 2000</span>
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="lmc-send-button"
                        id="lmcSendButton"
                        aria-label="Send message"
                    >
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="m22 2-7 20-4-9-9-4 20-7Z"/>
                            <path d="M22 2 11 13"/>
                        </svg>
                    </button>

                </form>

                <div class="lmc-composer-note">
                    <span>✦</span>
                    Messages are saved in this browser for demonstration.
                </div>

            </section>

        </div>

        {{-- BOTTOM BANNER --}}
        <div class="lmc-bottom-banner">
            <div>
                <span>SHOP WITH CONFIDENCE</span>
                <h2>Great finds. <em>Better conversations.</em></h2>
                <p>Stay in touch with sellers throughout your shopping journey.</p>
            </div>

            <a href="{{ url('/search') }}">
                Continue Shopping ↗
            </a>
        </div>

    </div>

    {{-- TOAST --}}
    <div class="lmc-toast" id="lmcToast" role="status" aria-live="polite"></div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/buyer/messages.js') }}?v=4" defer></script>
@endpush
