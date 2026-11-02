@extends('layouts.auth')


@section('title', 'Log In — Lumear')

@section('pageTitle', 'Log In')


@section('content')


<main class="auth-main">


    {{-- =====================================================
         LEFT SIDE
         E-COMMERCE BRAND / MARKETING AREA
         ===================================================== --}}

    <section
        class="auth-showcase"
        id="authShowcase"
    >

        {{-- MAIN HEADLINE ---------- --}}

        <h1 class="showcase-title">

            Find something
            <br>

            <span>worth loving.</span>

        </h1>



        {{-- DESCRIPTION ---------- --}}

        <p class="showcase-description">

            From everyday essentials to your newest finds,
            Lumear brings different products and categories
            together in one simple shopping experience.

        </p>



        {{-- FEATURES ---------- --}}

        <div class="showcase-features">


            {{-- FEATURE 1 ---------- --}}

            <div class="feature-pill">

                <div class="feature-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M12 3l7.5 3v5.25c0 4.35-3.15 8.4-7.5 9.75-4.35-1.35-7.5-5.4-7.5-9.75V6L12 3z"
                        />
                    </svg>

                </div>

                Secure shopping

            </div>



            {{-- FEATURE 2 ---------- --}}

            <div class="feature-pill">

                <div class="feature-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 7h11v10H3V7zm11 4h3l4 4v2h-7v-6z"
                        />

                        <circle cx="7" cy="18" r="2" />
                        <circle cx="17" cy="18" r="2" />
                    </svg>

                </div>

                Order tracking

            </div>



            {{-- FEATURE 3 ---------- --}}

            <div class="feature-pill">

                <div class="feature-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 6h16M4 12h16M4 18h10"
                        />
                    </svg>

                </div>

                Multiple categories

            </div>

        </div>



        {{-- =================================================
             FLOATING PRODUCT PREVIEW

             These are intentionally simple right now.
             Later we can replace these with real product
             images from your database.
             ================================================= --}}

        <div class="floating-area">


            {{-- PRODUCT 1 ---------- --}}

            <div class="product-float one">

                <div class="product-float-image">
                    🎧
                </div>

                <div class="product-float-info">

                    <small>
                        Electronics
                    </small>

                    <strong>
                        Everyday Tech
                    </strong>

                    <span>
                        Discover new finds
                    </span>

                </div>

            </div>



            {{-- PRODUCT 2 ---------- --}}

            <div class="product-float two">

                <div class="product-float-image">
                    👟
                </div>

                <div class="product-float-info">

                    <small>
                        Fashion
                    </small>

                    <strong>
                        New Styles
                    </strong>

                    <span>
                        Made for you
                    </span>

                </div>

            </div>



            {{-- PRODUCT 3 ---------- --}}

            <div class="product-float three">

                <div class="product-float-image">
                    🏠
                </div>

                <div class="product-float-info">

                    <small>
                        Home & Living
                    </small>

                    <strong>
                        Better Spaces
                    </strong>

                    <span>
                        For everyday living
                    </span>

                </div>

            </div>


        </div>


    </section>



    {{-- =====================================================
         RIGHT SIDE
         LOGIN
         ===================================================== --}}

    <section class="auth-form-side">


        <div class="auth-card">


            {{-- HEADING ---------- --}}

            <h2 class="auth-heading">
                Welcome back.
            </h2>


            <p class="auth-subheading">

                Sign in to your Lumear account
                and continue shopping.

            </p>



            {{-- =================================================
                 LOGIN FORM

                 IMPORTANT:
                 Your project currently has no real authentication.

                 The JavaScript temporarily handles the login,
                 just like your old AlpineJS implementation.
                 ================================================= --}}

            <form
                method="POST"
                action="{{ url('/login') }}"
                class="login-form"
                id="loginForm"
                data-home-url="{{ url('/home') }}"
            >

                @csrf



                {{-- EMAIL / CONTACT NUMBER ---------- --}}

                <div class="form-group">

                    <label
                        for="loginIdentity"
                        class="form-label"
                    >
                        Email or contact number
                    </label>


                    <div class="input-wrapper">


                        {{-- USER ICON ---------- --}}

                        <svg
                            class="input-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"
                            />

                        </svg>


                        <input
                            type="text"
                            id="loginIdentity"
                            name="login"
                            class="form-input"
                            placeholder="Enter your email or contact number"
                            autocomplete="username"
                            required
                        >

                    </div>

                </div>



                {{-- PASSWORD ---------- --}}

                <div class="form-group">

                    <label
                        for="password"
                        class="form-label"
                    >
                        Password
                    </label>


                    <div class="input-wrapper">


                        {{-- LOCK ICON ---------- --}}

                        <svg
                            class="input-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0119.5 12.75v6A2.25 2.25 0 0117.25 21H6.75A2.25 2.25 0 014.5 18.75v-6a2.25 2.25 0 012.25-2.25z"
                            />

                        </svg>



                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >



                        {{-- PASSWORD VISIBILITY BUTTON ---------- --}}

                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >


                            {{-- EYE OPEN ---------- --}}

                            <svg
                                id="eyeOpen"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.7"
                            >

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"
                                />

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="2.75"
                                />

                            </svg>



                            {{-- EYE CLOSED ---------- --}}

                            <svg
                                id="eyeClosed"
                                style="display: none;"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.7"
                            >

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.4A10.7 10.7 0 0112 4.2c6 0 9.75 7.8 9.75 7.8a18 18 0 01-3 4.2M6.1 6.1C3.7 8 2.25 12 2.25 12s3.75 7.8 9.75 7.8a10.6 10.6 0 004.1-.8"
                                />

                            </svg>


                        </button>


                    </div>

                </div>



                {{-- OPTIONS ---------- --}}

                <div class="form-options">


                    <label class="remember-option">

                        <input
                            type="checkbox"
                            name="remember"
                            checked
                        >

                        <span>
                            Remember me
                        </span>

                    </label>



                    <a
                        href="#"
                        class="forgot-link"
                    >
                        Forgot password?
                    </a>


                </div>



                {{-- LOGIN BUTTON ---------- --}}

                <button
                    type="submit"
                    class="login-button"
                    id="loginButton"
                >

                    <span>
                        SIGN IN
                    </span>

                </button>


            </form>



            {{-- =================================================
                 DIVIDER
                 ================================================= --}}

            <div class="auth-divider">

                <div class="auth-divider-line"></div>

                <span>
                    OR CONTINUE WITH
                </span>

                <div class="auth-divider-line"></div>

            </div>



            {{-- =================================================
                 GOOGLE LOGIN

                 Still demo-only because OAuth is not connected.
                 ================================================= --}}

            <button
                type="button"
                class="google-button"
                id="googleLogin"
                data-home-url="{{ url('/home') }}"
            >


                {{-- GOOGLE LOGO ---------- --}}

                <svg viewBox="0 0 24 24">

                    <path
                        fill="#4285F4"
                        d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.47a5.54 5.54 0 01-2.4 3.63v3h3.87c2.27-2.09 3.55-5.17 3.55-8.66z"
                    />

                    <path
                        fill="#34A853"
                        d="M12 24c3.24 0 5.96-1.08 7.94-2.93l-3.87-3c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.28v3.09A12 12 0 0012 24z"
                    />

                    <path
                        fill="#FBBC05"
                        d="M5.27 14.26a7.2 7.2 0 010-4.52V6.65H1.28a12 12 0 000 10.7l3.99-3.09z"
                    />

                    <path
                        fill="#EA4335"
                        d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0A12 12 0 001.28 6.65l3.99 3.09C6.22 6.86 8.87 4.75 12 4.75z"
                    />

                </svg>


                <span>
                    Continue with Google
                </span>


            </button>



            {{-- =================================================
                 REGISTER
                 ================================================= --}}

            <p class="signup-text">

                New to Lumear?

                <a href="{{ url('/register') }}">
                    Create an account
                </a>

            </p>



            {{-- =================================================
                 SECURITY MESSAGE
                 ================================================= --}}

            <div class="security-note">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M12 3l7.5 3v5.25c0 4.35-3.15 8.4-7.5 9.75-4.35-1.35-7.5-5.4-7.5-9.75V6L12 3z"
                    />

                </svg>

                Your information is protected.

            </div>


        </div>


    </section>


</main>


@endsection