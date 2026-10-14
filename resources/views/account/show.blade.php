@extends('layouts.app')

@section('title', 'My profile — Lumear')

@section('content')
    <section class="mx-auto max-w-2xl rise">
        <div class="rounded-2xl bg-cream-50 p-6 shadow-soft sm:p-8">
            <div class="flex items-center gap-4"><span class="grid h-14 w-14 place-items-center rounded-full bg-maroon-700 font-display text-2xl text-cream-50">{{ strtoupper(substr($user->name, 0, 1)) }}</span><div><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">My account</p><h1 class="mt-1 font-display text-4xl">Your profile</h1></div></div>
            @if ($user->isAdmin())
                <div class="mt-8 rounded-xl border border-sand-400/60 bg-cream-100/65 p-5"><p class="font-bold">Admin profile locked</p><p class="mt-2 text-sm text-maroon-900/65">Administrative identities cannot be edited from the marketplace profile page. This protects access control and audit records.</p><dl class="mt-5 space-y-3 text-sm"><div><dt class="text-maroon-900/55">Name</dt><dd class="mt-1 font-bold">{{ $user->name }}</dd></div><div><dt class="text-maroon-900/55">Email address</dt><dd class="mt-1 font-bold">{{ $user->email }}</dd></div></dl></div>
            @else
            <form class="mt-8 space-y-5" method="post" action="{{ route('account.update') }}">
                @csrf
                @method('PATCH')
                <label class="label">Name<input class="field mt-2" name="name" value="{{ old('name', $user->name) }}" required></label>
                <label class="label">Mobile number<input class="field mt-2" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="09XXXXXXXXX"></label>
                <label class="label">Email address<input class="field mt-2 cursor-not-allowed bg-cream-100" value="{{ $user->email }}" disabled><span class="mt-1 block text-xs font-normal text-maroon-900/55">Email changes are not available here because your sign-in and verification are tied to this address.</span></label>
                <button class="glow w-full rounded-xl bg-maroon-700 p-3.5 text-sm font-bold text-cream-50">Save profile</button>
            </form>
            @endif
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            @if ($user->isBuyer())
                <a class="rounded-xl border border-sand-400/60 bg-cream-50 p-5 text-sm font-bold hover:border-maroon-700" href="{{ route('buyer.address.create') }}">Delivery address<span class="mt-1 block text-xs font-normal text-maroon-900/60">Add or replace the address used for COD orders.</span></a>
            @endif
            @if ($user->isSeller())
                <a class="rounded-xl border border-sand-400/60 bg-cream-50 p-5 text-sm font-bold hover:border-maroon-700" href="{{ route('seller.status') }}">Seller Centre<span class="mt-1 block text-xs font-normal text-maroon-900/60">Manage your store, onboarding, and settings.</span></a>
            @endif
            @if ($user->isAdmin())
                <a class="rounded-xl border border-sand-400/60 bg-cream-50 p-5 text-sm font-bold hover:border-maroon-700" href="{{ route('admin.2fa.challenge') }}">Admin security<span class="mt-1 block text-xs font-normal text-maroon-900/60">Verify your authenticator before entering the Admin Centre.</span></a>
            @endif
        </div>
    </section>
@endsection
