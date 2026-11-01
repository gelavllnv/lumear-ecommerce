@extends('layouts.seller')

@section('seller-content')
    <div class="seller-page">
    <div class="seller-page-header">
        <div>
            <p class="seller-page-eyebrow">Customer voice</p>
            <h1>Reviews</h1>
            <p>Read customer feedback and improve future listings.</p>
        </div>
        <form class="flex items-center gap-2" method="get"><label class="sr-only" for="rating">Rating</label><select class="field !w-auto" id="rating" name="rating" onchange="this.form.submit()"><option value="">All ratings</option>@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}" @selected(request('rating') == $rating)>{{ $rating }} star{{ $rating === 1 ? '' : 's' }}</option>@endfor</select><span class="seller-table-status">{{ $reviews->total() }} total</span></form>
    </div>

    @forelse ($reviews as $review)
        <article class="seller-operation-card mb-4">
            <div class="flex justify-between gap-4">
                <div>
                    <p class="font-bold">{{ $review->buyer->name }}</p>
                    <p class="text-sm text-maroon-900/55 mt-1">{{ $review->product->name }}</p>
                </div>
                <span class="text-maroon-700 tracking-wider">{{ str_repeat('★', $review->rating) }}</span>
            </div>
            @if ($review->body)
                <p class="mt-4 leading-relaxed text-maroon-900/75">{{ $review->body }}</p>
            @endif
            @if($review->reply)
                <div class="mt-4 rounded-xl border border-maroon-700/30 bg-maroon-900/5 p-4"><p class="text-xs font-bold uppercase tracking-wider text-maroon-700">Your response</p><p class="mt-2 text-sm">{{ $review->reply->body }}</p></div>
            @endif
            <form class="seller-operation-form mt-4 flex flex-wrap gap-3" method="post" action="{{ route('seller.reviews.reply', $review) }}">@csrf<label class="sr-only" for="reply-{{ $review->id }}">Seller response</label><input id="reply-{{ $review->id }}" class="field flex-1" name="body" maxlength="500" value="{{ old('body', $review->reply?->body) }}" placeholder="Reply to this review" required><button class="rounded-xl px-4 py-2 text-sm font-bold">{{ $review->reply ? 'Update response' : 'Reply' }}</button></form>
            <p class="mt-4 text-xs text-maroon-900/45">{{ $review->created_at->toFormattedDateString() }}</p>
        </article>
    @empty
        <div class="seller-surface seller-empty-state">
            <b>No reviews yet.</b>
            <p>Reviews from your customers will appear here.</p>
        </div>
    @endforelse
    </div>
@endsection
