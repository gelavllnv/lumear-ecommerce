
@extends('layouts.logistics')

@section('title', 'Messages | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/messages.css') }}">
@endpush

@section('content')
<div class="lm-page" id="logisticsMessagesApp">

    {{-- PAGE HEADER --}}
    <header class="lm-page-header">
        <div>
            <p class="lm-eyebrow">LOGISTICS / COMMUNICATION</p>
            <h1>Messages</h1>
            <p>Communicate with riders, sellers, and administrators in one place.</p>
        </div>

        <div class="lm-header-actions">
            <span class="lm-connection">
                <span></span>
                Demo Messaging
            </span>

            <button type="button" class="lm-btn lm-btn-outline" id="lmNewMessage">
                + New Message
            </button>
        </div>
    </header>

    {{-- MESSAGE STATISTICS --}}
    <section class="lm-stats">
        <article class="lm-stat">
            <span class="lm-stat-icon">✉</span>
            <div>
                <small>Total Conversations</small>
                <strong id="lmTotalConversations">0</strong>
            </div>
        </article>

        <article class="lm-stat">
            <span class="lm-stat-icon">●</span>
            <div>
                <small>Unread Conversations</small>
                <strong id="lmUnreadCount">0</strong>
            </div>
        </article>

        <article class="lm-stat">
            <span class="lm-stat-icon">♙</span>
            <div>
                <small>Rider Conversations</small>
                <strong id="lmRiderCount">0</strong>
            </div>
        </article>
    </section>

    {{-- MESSAGING WORKSPACE --}}
    <section class="lm-workspace" aria-label="Messaging workspace">

        {{-- LEFT: CONVERSATIONS --}}
        <aside class="lm-conversations" id="lmConversationsPanel">
            <div class="lm-conversations-heading">
                <div>
                    <p class="lm-eyebrow">INBOX</p>
                    <h2>Conversations</h2>
                </div>
                <span class="lm-count" id="lmInboxCount">0</span>
            </div>

            <div class="lm-search">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-4-4"/>
                </svg>

                <input
                    type="search"
                    id="lmSearch"
                    placeholder="Search conversations..."
                    aria-label="Search conversations"
                >
            </div>

            <div class="lm-filter-row">
                <button type="button" class="lm-filter active"
                        data-lm-filter="all">All</button>
                <button type="button" class="lm-filter"
                        data-lm-filter="unread">Unread</button>
                <button type="button" class="lm-filter"
                        data-lm-filter="Rider">Riders</button>
                <button type="button" class="lm-filter"
                        data-lm-filter="Seller">Sellers</button>
                <button type="button" class="lm-filter"
                        data-lm-filter="Admin">Admin</button>
            </div>

            <div class="lm-conversation-list" id="lmConversationList"></div>

            <div class="lm-list-empty" id="lmListEmpty" hidden>
                No conversations found.
            </div>
        </aside>

        {{-- RIGHT: CHAT --}}
        <div class="lm-chat-panel" id="lmChatPanel">

            <div class="lm-chat-header">
                <div class="lm-chat-identity">
                    <button type="button" class="lm-back"
                            id="lmBack" aria-label="Back to conversations">
                        ←
                    </button>

                    <div class="lm-avatar" id="lmChatAvatar">--</div>

                    <div class="lm-chat-person">
                        <strong id="lmChatName">Select a conversation</strong>
                        <span id="lmChatRole">No conversation selected</span>
                    </div>
                </div>

                <div class="lm-chat-actions">
                    <button type="button" class="lm-icon-button"
                            id="lmMarkRead" title="Mark as read"
                            aria-label="Mark conversation as read">
                        ✓
                    </button>

                    <button type="button" class="lm-icon-button"
                            id="lmChatInfo" title="Conversation details"
                            aria-label="Conversation details">
                        ⓘ
                    </button>
                </div>
            </div>

            <div class="lm-messages" id="lmMessageList"
                 role="log" aria-live="polite" aria-label="Chat messages"></div>

            <form class="lm-composer" id="lmMessageForm">
                <textarea
                    id="lmMessageInput"
                    rows="1"
                    maxlength="2000"
                    placeholder="Type your message..."
                    aria-label="Type your message"
                ></textarea>

                <button type="submit" class="lm-send" id="lmSendButton"
                        aria-label="Send message">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         aria-hidden="true">
                        <path d="m22 2-7 20-4-9-9-4Z"/>
                        <path d="M22 2 11 13"/>
                    </svg>
                </button>
            </form>

            <p class="lm-composer-hint">
                Press Enter to send. Shift + Enter for a new line.
            </p>
        </div>
    </section>

    <p class="lm-demo-note">
        Frontend demonstration only. Messages are stored in this browser and
        are not yet delivered to real users or the Flutter Rider application.
    </p>

    {{-- NEW CONVERSATION MODAL --}}
    <div class="lm-modal" id="lmNewModal" aria-hidden="true">
        <div class="lm-dialog" role="dialog" aria-modal="true"
             aria-labelledby="lmNewTitle">
            <div class="lm-dialog-header">
                <div>
                    <p class="lm-eyebrow">COMMUNICATION</p>
                    <h2 id="lmNewTitle">New Conversation</h2>
                    <p>Choose a contact to start messaging.</p>
                </div>
                <button type="button" class="lm-close" data-lm-close
                        aria-label="Close">×</button>
            </div>

            <div class="lm-contact-list" id="lmContactList"></div>

            <div class="lm-dialog-footer">
                <button type="button" class="lm-btn lm-btn-outline"
                        data-lm-close>Cancel</button>
            </div>
        </div>
    </div>

    {{-- CONVERSATION INFO MODAL --}}
    <div class="lm-modal" id="lmInfoModal" aria-hidden="true">
        <div class="lm-dialog lm-dialog-small" role="dialog" aria-modal="true"
             aria-labelledby="lmInfoTitle">
            <div class="lm-dialog-header">
                <div>
                    <p class="lm-eyebrow">CONVERSATION DETAILS</p>
                    <h2 id="lmInfoTitle">Contact Information</h2>
                </div>
                <button type="button" class="lm-close" data-lm-close
                        aria-label="Close">×</button>
            </div>

            <div class="lm-info-grid" id="lmInfoGrid"></div>

            <div class="lm-dialog-footer">
                <button type="button" class="lm-btn lm-btn-outline"
                        data-lm-close>Close</button>
            </div>
        </div>
    </div>

    <div class="lm-toast" id="lmToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/messages.js') }}" defer></script>
@endpush
