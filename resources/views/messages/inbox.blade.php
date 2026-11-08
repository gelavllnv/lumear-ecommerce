@extends(auth()->user()->isSeller() ? 'layouts.seller' : 'layouts.app')

@if (auth()->user()->isBuyer())
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/buyer/messaging-live.css') }}?v=1">
    @endpush
@endif

@section(auth()->user()->isSeller() ? 'seller-content' : 'content')
    @if (auth()->user()->isBuyer())
        <section class="buyer-messages" aria-labelledby="messages-title">
            <header class="buyer-messages-heading">
                <div>
                    <p class="buyer-messages-eyebrow">Buyer inbox</p>
                    <h1 id="messages-title">Messages</h1>
                    <p>Ask a seller about an item or keep up with an existing conversation.</p>
                </div>
                <a class="buyer-messages-action" href="{{ route('shop') }}">Browse products</a>
            </header>

            <div class="buyer-conversation-list" role="list">
                @forelse ($conversations as $conversation)
                    @php
                        $otherParty = $conversation->seller->name;
                        $latestMessage = $conversation->messages->first();
                        $unreadCount = $conversation->messages->filter(
                            fn ($message) => $message->sender_id !== auth()->id() && is_null($message->read_at)
                        )->count();
                    @endphp
                    <a class="buyer-conversation-card" href="{{ route('messages.show', $conversation) }}" role="listitem">
                        <span class="buyer-conversation-avatar" aria-hidden="true">{{ strtoupper(substr($otherParty, 0, 1)) }}</span>
                        <span class="buyer-conversation-copy">
                            <span class="buyer-conversation-topline">
                                <strong>{{ $otherParty }}</strong>
                                <time datetime="{{ optional($conversation->last_message_at)->toIso8601String() }}">
                                    {{ $conversation->last_message_at?->diffForHumans() ?? 'New' }}
                                </time>
                            </span>
                            <span class="buyer-conversation-preview">
                                {{ $latestMessage?->body ?: ($latestMessage && $latestMessage->attachments->isNotEmpty() ? 'Sent an image' : 'Start a conversation') }}
                            </span>
                        </span>
                        @if ($unreadCount > 0)
                            <span class="buyer-unread-badge" aria-label="{{ $unreadCount }} unread messages">{{ $unreadCount }}</span>
                        @endif
                    </a>
                @empty
                    <div class="buyer-messages-empty">
                        <span aria-hidden="true">✦</span>
                        <h2>No conversations yet</h2>
                        <p>Open any product and use “Message seller” to ask a question before ordering.</p>
                        <a href="{{ route('shop') }}">Find a product</a>
                    </div>
                @endforelse
            </div>
        </section>
    @else
        <div class="seller-page seller-messages-page">
            <header class="seller-page-header">
                <div><p class="seller-page-eyebrow">Seller communication</p><h1>Messages</h1><p>Keep buyer questions and order communication safely in Lumear.</p></div>
                <span class="seller-message-connection"><i></i> Buyer inbox</span>
            </header>
            <section class="seller-messages-workspace" aria-label="Seller messaging workspace">
                <aside class="seller-conversation-panel">
                    <div class="seller-conversation-panel-heading"><div><p class="seller-page-eyebrow">Inbox</p><h2>Conversations</h2></div><span>{{ $conversations->count() }}</span></div>
                    <div class="seller-conversation-list">
                        @forelse ($conversations as $conversation)
                            @php
                                $latestMessage = $conversation->messages->first();
                                $unreadCount = $conversation->messages->filter(fn ($message) => $message->sender_id !== auth()->id() && is_null($message->read_at))->count();
                            @endphp
                            <a class="seller-conversation-row" href="{{ route('messages.show', $conversation) }}">
                                <span class="seller-conversation-avatar">{{ strtoupper(substr($conversation->buyer->name, 0, 1)) }}</span>
                                <span class="seller-conversation-copy"><span class="seller-conversation-top"><strong>{{ $conversation->buyer->name }}</strong><time>{{ $conversation->last_message_at?->diffForHumans() ?? 'New' }}</time></span><small>{{ $latestMessage?->body ?: ($latestMessage && $latestMessage->attachments->isNotEmpty() ? 'Sent an image' : 'Start a conversation') }}</small></span>
                                @if($unreadCount > 0)<b class="seller-message-unread">{{ $unreadCount }}</b>@endif
                            </a>
                        @empty
                            <div class="seller-conversation-empty">No conversations yet.</div>
                        @endforelse
                    </div>
                </aside>
                <div class="seller-message-placeholder"><span>✉</span><strong>Select a conversation</strong><p>Choose a buyer from the inbox to view and reply to their messages.</p></div>
            </section>
        </div>
    @endif
@endsection
