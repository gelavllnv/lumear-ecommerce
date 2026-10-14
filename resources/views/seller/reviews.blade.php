@extends('layouts.seller')

@section('seller-content')
    <div class="flex items-end justify-between gap-4 mb-6">
        <div>
            <p class="text-xs uppercase tracking-[.18em] font-bold text-maroon-700">Customer voice</p>
            <h1 class="font-display text-4xl mt-2">Reviews</h1>
        </div>
        <span class="rounded-full bg-cream-200 px-4 py-2 text-sm font-bold">{{ $reviews->count() }} total</span>
    </div>

    @forelse ($reviews as $review)
        <article class="bg-cream-50 rounded-2xl p-5 shadow-soft mb-4">
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
            <p class="mt-4 text-xs text-maroon-900/45">{{ $review->created_at->toFormattedDateString() }}</p>
        </article>
    @empty
        <div class="bg-cream-50 rounded-2xl p-12 text-center shadow-soft">
            <p class="font-display text-2xl">No reviews yet.</p>
            <p class="text-sm text-maroon-900/55 mt-2">Reviews from your customers will appear here.</p>
        </div>
    @endforelse
@endsection
