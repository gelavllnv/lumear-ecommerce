@extends('layouts.seller')

@section('seller-content')
    <div class="seller-page seller-notifications-page">
        <header class="seller-page-header">
            <div>
                <p class="seller-page-eyebrow">Seller updates</p>
                <h1>Notifications</h1>
                <p>Keep track of order, stock, promotion, and marketplace updates.</p>
            </div>
        </header>

        <section class="seller-surface seller-notifications-list">
            @forelse($notifications as $notification)
                <article class="seller-notification-item">
                    <span class="seller-notification-icon" aria-hidden="true">●</span>
                    <div>
                        <strong>{{ $notification->data['title'] }}</strong>
                        <p>{{ $notification->data['message'] }}</p>
                    </div>
                </article>
            @empty
                <div class="seller-empty-state">
                    <b>No notifications yet.</b>
                    <p>Important updates for your shop will appear here.</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection
