@extends('layouts.seller')

@section('title', 'My profile — Lumear Seller Centre')

@section('seller-content')
    <div class="seller-account-page">
        <header class="seller-account-heading">
            <div><p>Account settings</p><h1>My profile</h1><span>Manage the operator details for {{ $seller?->name ?? 'your shop' }}.</span></div>
            <a href="{{ route('seller.settings') }}">Store settings <span>→</span></a>
        </header>
        <div class="seller-account-grid">
            <section class="seller-profile-card">
                <div class="seller-profile-identity"><span>{{ strtoupper(substr($user->name, 0, 1)) }}</span><div><p>Seller operator</p><h2>{{ $user->name }}</h2><small>{{ $user->email }}</small></div><b><i></i>{{ ucfirst($seller?->status ?? 'active') }}</b></div>
                <form method="post" action="{{ route('account.update') }}" class="seller-profile-form">@csrf @method('PATCH')
                    <label>Full name<input class="field" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required></label>
                    <label>Mobile number<input class="field" name="phone" value="{{ old('phone', $user->phone) }}" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX"></label>
                    <label class="seller-profile-email">Email address<input class="field" value="{{ $user->email }}" disabled><span>Email is locked because it is used for sign-in and verification.</span></label>
                    <button class="seller-account-save" type="submit">Save profile changes</button>
                </form>
            </section>
            <aside class="seller-account-side">
                <section><p>Store identity</p><h2>{{ $seller?->name }}</h2><span>Shop names are locked after onboarding to protect buyer trust and order records.</span><a href="{{ route('seller.settings') }}">Edit store details <b>→</b></a></section>
                <section><p>Account security</p><h2>Sign-in protection</h2><span>Use your verified email and password to access the Seller Centre. Contact support if you need help with access.</span><a href="{{ route('seller.status') }}">View seller status <b>→</b></a></section>
            </aside>
        </div>
    </div>
@endsection
