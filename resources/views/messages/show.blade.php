@extends(auth()->user()->isSeller() ? 'layouts.seller' : 'layouts.app')

@if (auth()->user()->isBuyer())
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/buyer/messaging-live.css') }}?v=1">
    @endpush
    @push('scripts')
        <script src="{{ asset('js/buyer/live-messages.js') }}?v=1" defer></script>
    @endpush
@endif

@section(auth()->user()->isSeller() ? 'seller-content' : 'content')
    @if (auth()->user()->isBuyer())
        @php($seller = $conversation->seller)
        <section class="buyer-messages buyer-message-thread" aria-labelledby="conversation-title">
            <a class="buyer-message-back" href="{{ route('messages.index') }}">← All messages</a>

            <div class="buyer-message-shell">
                <header class="buyer-thread-header">
                    <span class="buyer-conversation-avatar" aria-hidden="true">{{ strtoupper(substr($seller->name, 0, 1)) }}</span>
                    <div>
                        <p class="buyer-messages-eyebrow">Conversation with</p>
                        <h1 id="conversation-title">{{ $seller->name }}</h1>
                        <p>Replies are visible only to you and this seller.</p>
                    </div>
                    <a class="buyer-thread-shop-link" href="{{ route('shops.show', $seller) }}">Visit shop</a>
                </header>

                <div class="buyer-thread-messages" aria-live="polite">
                    @forelse ($conversation->messages as $message)
                        @php($isOwnMessage = $message->sender_id === auth()->id())
                        <article class="buyer-chat-row {{ $isOwnMessage ? 'is-own' : 'is-seller' }}">
                            <div class="buyer-chat-bubble">
                                @if ($message->product)
                                    <a class="buyer-chat-product" href="{{ route('products.show', $message->product) }}">
                                        <span class="buyer-chat-product-image">
                                            @if ($message->product->images->first())
                                                <img src="{{ route('media.show', ['path' => $message->product->images->first()->path]) }}" alt="{{ $message->product->name }}">
                                            @else
                                                <span aria-hidden="true">✦</span>
                                            @endif
                                        </span>
                                        <span>
                                            <small>About this product</small>
                                            <strong>{{ $message->product->name }}</strong>
                                        </span>
                                    </a>
                                @endif

                                @if ($message->body !== '')
                                    <p class="buyer-chat-text">{{ $message->body }}</p>
                                @endif

                                @if ($message->attachments->isNotEmpty())
                                    <div class="buyer-chat-attachments">
                                        @foreach ($message->attachments as $attachment)
                                            <a href="{{ route('media.show', ['path' => $attachment->path]) }}" target="_blank" rel="noopener">
                                                <img src="{{ route('media.show', ['path' => $attachment->path]) }}" alt="Open shared image">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, g:i A') }}</time>
                        </article>
                    @empty
                        <div class="buyer-thread-empty">
                            <span aria-hidden="true">✦</span>
                            <p>Ask {{ $seller->name }} a question to begin.</p>
                        </div>
                    @endforelse
                </div>

                <form class="buyer-message-composer" id="buyerMessageForm" method="post" enctype="multipart/form-data" action="{{ route('messages.send', $conversation) }}">
                    @csrf
                    <div class="buyer-message-input-wrap">
                        <label class="sr-only" for="buyer-message-body">Your message</label>
                        <textarea id="buyer-message-body" name="body" maxlength="1000" rows="2" placeholder="Write a message…">{{ old('body') }}</textarea>
                        <div class="buyer-message-composer-meta">
                            <label class="buyer-image-picker" for="buyer-message-images">
                                <span aria-hidden="true">▧</span> Add images
                            </label>
                            <span id="buyerMessageCharacterCount">{{ mb_strlen(old('body', '')) }} / 1000</span>
                        </div>
                    </div>
                    <input id="buyer-message-images" class="sr-only" type="file" name="attachments[]" accept="image/jpeg,image/png" multiple>
                    <button type="submit">Send</button>
                    <div class="buyer-image-preview" id="buyerImagePreview" hidden></div>

                    @error('body')
                        <p class="buyer-composer-error">{{ $message }}</p>
                    @enderror
                    @error('attachments')
                        <p class="buyer-composer-error">{{ $message }}</p>
                    @enderror
                    @foreach ($errors->get('attachments.*') as $attachmentErrors)
                        <p class="buyer-composer-error">{{ $attachmentErrors[0] }}</p>
                    @endforeach
                </form>
            </div>
        </section>
    @else
        <div class="seller-page seller-message-thread">
            <a class="seller-table-action" href="{{ route('seller.messages') }}">← All messages</a>
            <div class="seller-surface mt-4 overflow-hidden">
                <header class="seller-surface-header">
                    <div>
                        <p>Conversation</p>
                        <h2>{{ $conversation->buyer->name }}</h2>
                    </div>
                    <span class="seller-table-status">Product questions</span>
                </header>
                <div class="space-y-4 p-5">
                    @forelse ($conversation->messages as $message)
                        <div class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm {{ $message->sender_id === auth()->id() ? 'bg-maroon-700 text-cream-50' : 'bg-cream-100 text-maroon-900' }}">
                                @if ($message->product)
                                    <div class="mb-3 flex items-center gap-2 rounded-lg border border-current/20 p-2">
                                        <div class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded bg-cream-50/10">
                                            @if ($message->product->images->first())
                                                <img class="h-full w-full object-cover" src="{{ route('media.show', ['path' => $message->product->images->first()->path]) }}" alt="{{ $message->product->name }}">
                                            @else
                                                <span>✦</span>
                                            @endif
                                        </div>
                                        <div><p class="text-[10px] font-bold uppercase tracking-wider opacity-65">Regarding product</p><p class="font-bold">{{ $message->product->name }}</p></div>
                                    </div>
                                @endif
                                @if ($message->body !== '')<p>{{ $message->body }}</p>@endif
                                @if ($message->attachments->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($message->attachments as $attachment)
                                            <a href="{{ route('media.show', ['path' => $attachment->path]) }}" target="_blank" rel="noopener"><img class="h-20 w-20 rounded-lg object-cover" src="{{ route('media.show', ['path' => $attachment->path]) }}" alt="Message attachment"></a>
                                        @endforeach
                                    </div>
                                @endif
                                <p class="mt-2 text-[10px] opacity-60">{{ $message->created_at->format('M j, g:i A') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-maroon-900/60">Start the conversation below.</p>
                    @endforelse
                </div>
                <form class="seller-operation-form flex flex-wrap gap-3 border-t border-sand-400/25 p-4" method="post" enctype="multipart/form-data" action="{{ route('messages.send', $conversation) }}">
                    @csrf
                    <label class="sr-only" for="seller-message-body">Message</label>
                    <textarea id="seller-message-body" class="field min-h-11 flex-1" name="body" maxlength="1000" placeholder="Write a message..."></textarea>
                    <label class="inline-flex cursor-pointer items-center rounded-xl border border-sand-400/45 px-3 text-xs font-bold">Image<input class="hidden" type="file" name="attachments[]" accept="image/jpeg,image/png" multiple></label>
                    <button class="rounded-xl px-5 py-3 text-sm font-bold">Send</button>
                </form>
            </div>
        </div>
    @endif
@endsection
