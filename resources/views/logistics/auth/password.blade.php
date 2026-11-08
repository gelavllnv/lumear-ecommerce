@extends('layouts.logistics-auth')

@section(
    'title',
    $mode === 'reset'
        ? 'Reset Password | LUMEAR Logistics'
        : 'Forgot Password | LUMEAR Logistics'
)

@section('content')
<div class="login-layout">
    <section class="logistics-intro">
        <div class="portal-badge">
            LOGISTICS PARTNER PORTAL
        </div>

        <h1 class="intro-title">
            Recover your access.
            <span>Return to your workspace.</span>
        </h1>

        <p class="intro-description">
            Use the email associated with your logistics account
            to reset your password.
        </p>
    </section>

    <section class="logistics-form-area">
        <div class="logistics-card">
            <div class="card-header">
                <span class="card-eyebrow">
                    ACCOUNT RECOVERY
                </span>

                <h2>
                    {{ $mode === 'reset'
                        ? 'Set a new password.'
                        : 'Forgot password?' }}
                </h2>

                <p>
                    {{ $mode === 'reset'
                        ? 'Choose a new password for your account.'
                        : 'Enter your registered email to request a reset link.' }}
                </p>
            </div>

            @if(session('status'))
                <div
                    role="status"
                    style="
                        padding: 14px;
                        margin-bottom: 18px;
                        border: 1px solid #bdd9c5;
                        border-radius: 8px;
                        background: #edf8f0;
                        color: #245b35;
                        line-height: 1.6;
                    "
                >
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div
                    role="alert"
                    style="
                        padding: 14px;
                        margin-bottom: 18px;
                        border: 1px solid #e0aaaa;
                        border-radius: 8px;
                        background: #fff0f0;
                        color: #9b242b;
                        line-height: 1.6;
                    "
                >
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                class="logistics-form"
                method="POST"
                action="{{ route(
                    $mode === 'reset'
                        ? 'logistics.password.update'
                        : 'logistics.password.email'
                ) }}"
            >
                @csrf

                @if($mode === 'reset')
                    <input
                        type="hidden"
                        name="token"
                        value="{{ $token }}"
                    >
                @endif

                <div class="form-group">
                    <label for="recoveryEmail">
                        Email address
                    </label>

                    <div class="input-container">
                        <input
                            id="recoveryEmail"
                            type="email"
                            name="email"
                            maxlength="255"
                            required
                            autocomplete="email"
                            value="{{ $mode === 'reset'
                                ? $email
                                : old('email') }}"
                            @if($mode === 'reset')
                                readonly
                            @else
                                autofocus
                            @endif
                            placeholder="name@business.com"
                        >
                    </div>
                </div>

                @if($mode === 'reset')
                    <div class="form-group">
                        <label for="newPassword">
                            New password
                        </label>

                        <div class="input-container">
                            <input
                                id="newPassword"
                                type="password"
                                name="password"
                                required
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                autofocus
                                placeholder="At least 8 characters"
                            >

                            <button
                                type="button"
                                class="password-button"
                                data-password-target="newPassword"
                                aria-label="Show password"
                            >
                                <span aria-hidden="true">◉</span>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">
                            Confirm new password
                        </label>

                        <div class="input-container">
                            <input
                                id="confirmPassword"
                                type="password"
                                name="password_confirmation"
                                required
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                placeholder="Repeat your new password"
                            >

                            <button
                                type="button"
                                class="password-button"
                                data-password-target="confirmPassword"
                                aria-label="Show password"
                            >
                                <span aria-hidden="true">◉</span>
                            </button>
                        </div>
                    </div>
                @endif

                <button
                    type="submit"
                    class="primary-button"
                >
                    <span>
                        {{ $mode === 'reset'
                            ? 'Reset Password'
                            : 'Send Reset Link' }}
                    </span>
                </button>
            </form>

            <div
                style="
                    margin-top: 20px;
                    display: flex;
                    gap: 16px;
                    flex-wrap: wrap;
                "
            >
                <a href="{{ route('logistics.login') }}">
                    Back to sign in
                </a>

                @if($mode === 'reset')
                    <a href="{{ route('logistics.password.request') }}">
                        Request a new link
                    </a>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection