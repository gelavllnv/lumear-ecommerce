@extends('layouts.app')

@section('title', 'Need help? — Lumear')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buyer/support.css') }}?v=2">
@endpush

@section('content')
    <section class="buyer-support" aria-labelledby="support-title">
        <div class="buyer-support-card">
            <div class="buyer-support-heading">
                <div>
                    <p class="buyer-support-eyebrow">Lumear Assist</p>
                    <h1 id="support-title">Need help?</h1>
                    <p class="buyer-support-intro">Start with a quick answer about payment, delivery, orders, or seller messages. We’ll point you to the next useful place.</p>
                </div>
                <div class="buyer-support-presence"><span aria-hidden="true"></span><strong>Help is ready</strong><small>Instant guidance</small></div>
            </div>

            <div class="buyer-support-starters" aria-label="Common help questions">
                <button type="button" data-support-question="How do I pay with Cash on Delivery?">Cash on Delivery</button>
                <button type="button" data-support-question="Where is my order and how do I track it?">Track an order</button>
                <button type="button" data-support-question="How do I update my delivery address?">Delivery address</button>
                <button type="button" data-support-question="How do I message a seller?">Message a seller</button>
            </div>

            <form method="post" action="{{ route('support.ask') }}" class="buyer-support-form">
                @csrf
                <label for="support-question">Tell us what you need</label>
                <textarea id="support-question" name="question" maxlength="500" rows="4" placeholder="For example: How do I message a seller about a product?" aria-describedby="support-question-hint">{{ old('question') }}</textarea>
                <div class="buyer-support-form-footer"><span id="support-question-hint">Be as specific as you can. You can ask about COD, delivery addresses, order tracking, or seller messages.</span><span id="support-count" aria-live="polite">{{ mb_strlen(old('question', '')) }}/500</span></div>
                @error('question')<p class="buyer-support-error" role="alert">{{ $message }}</p>@enderror
                <button type="submit">Get help <span aria-hidden="true">→</span></button>
            </form>

            @if (session('support_answer'))
                @php($answer = session('support_answer'))
                <article class="buyer-support-answer" aria-live="polite" tabindex="-1" id="support-answer">
                    <p class="buyer-support-answer-label">Suggested next step</p>
                    <h2>{{ $answer['title'] }}</h2>
                    <p>{{ $answer['body'] }}</p>
                    <a href="{{ $answer['action_url'] }}">{{ $answer['action_label'] }} <span aria-hidden="true">→</span></a>
                </article>
            @endif
        </div>

        <div class="buyer-support-topics" aria-label="Useful help links">
            <a href="{{ route('orders') }}"><span class="buyer-support-topic-icon" aria-hidden="true">◷</span><strong>Track my order</strong><span>See shipping and delivery updates.</span><b>Open orders <i>→</i></b></a>
            <a href="{{ route('buyer.address.create') }}"><span class="buyer-support-topic-icon" aria-hidden="true">⌂</span><strong>Delivery address</strong><span>Add or update the address used for COD.</span><b>Manage address <i>→</i></b></a>
            <a href="{{ route('messages.index') }}"><span class="buyer-support-topic-icon" aria-hidden="true">✉</span><strong>Message a seller</strong><span>Ask a product question and share images.</span><b>Open messages <i>→</i></b></a>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    (() => {
        const question = document.querySelector('#support-question');
        const count = document.querySelector('#support-count');
        const syncCount = () => { if (question && count) count.textContent = `${question.value.length}/500`; };
        document.querySelectorAll('[data-support-question]').forEach((button) => button.addEventListener('click', () => {
            question.value = button.dataset.supportQuestion;
            question.focus();
            syncCount();
        }));
        question?.addEventListener('input', syncCount);
        document.querySelector('#support-answer')?.focus();
    })();
</script>
@endpush
