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
                        $otherParty = $conversation->rider_id
                            ? (($conversation->rider?->user?->name ?? 'Delivery rider') . ' · ' . ($conversation->shipment?->tracking_code ?? 'Parcel'))
                            : ($conversation->seller?->name ?? 'Shop');
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
        <div class="seller-page mx-auto max-w-4xl">
            <header class="seller-page-header">
                <div>
                    <p class="seller-page-eyebrow">Direct messages</p>
                    <h1>Messages</h1>
                    <p>Keep buyer questions and order communication safely in Lumear.</p>
                </div>
            </header>
            <div class="seller-surface seller-message-list">
                @forelse ($conversations as $conversation)
                    <a class="flex items-center justify-between gap-4 border-b border-sand-400/20 p-5 transition" href="{{ route('messages.show', $conversation) }}">
                        <div>
                            <strong>{{ $conversation->buyer->name }}</strong>
                            <p class="mt-1 text-xs text-maroon-900/55">{{ $conversation->last_message_at?->diffForHumans() ?? 'New conversation' }}</p>
                        </div>
                        <span class="seller-table-action">Open →</span>
                    </a>
                @empty
                    <div class="seller-empty-state">
                        <b>No messages yet.</b>
                        <p>Buyer questions and seller replies will appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
@endsection
