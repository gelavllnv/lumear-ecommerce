@extends('layouts.logistics-auth')


@section('title', 'Logistics Portal — Lumear')


@section('content')


<div class="login-layout">


    {{-- =====================================================
         LEFT SIDE
         ===================================================== --}}

    <section class="logistics-intro">


        <div class="portal-badge">

            <span class="portal-badge-icon">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M3 6h11v11H3z"/>
                    <path d="M14 10h4l3 3v4h-7z"/>
                    <circle cx="7" cy="18" r="2"/>
                    <circle cx="18" cy="18" r="2"/>
                </svg>

            </span>

            LOGISTICS PARTNER PORTAL

        </div>



        <h1 class="intro-title">

            Move every parcel
            <span>with confidence.</span>

        </h1>



        <p class="intro-description">

            Manage parcel operations, sorting, rider assignments,
            and deliveries through one organized logistics workspace.

        </p>



        {{-- Features --}}
        <div class="intro-features">


            <div class="intro-feature">

                <div class="intro-feature-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                        <path d="m3.3 7 8.7 5 8.7-5"/>
                        <path d="M12 22V12"/>
                    </svg>

                </div>

                <div>
                    <strong>Parcel Operations</strong>
                    <span>Receive, sort and manage parcels.</span>
                </div>

            </div>



            <div class="intro-feature">

                <div class="intro-feature-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M3 21v-2a6 6 0 0 1 12 0v2"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        <path d="M21 21v-2a6 6 0 0 0-4.5-5.8"/>
                    </svg>

                </div>

                <div>
                    <strong>Rider Management</strong>
                    <span>Manage riders and delivery assignments.</span>
                </div>

            </div>



            <div class="intro-feature">

                <div class="intro-feature-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>

                </div>

                <div>
                    <strong>Delivery Monitoring</strong>
                    <span>Follow parcel progress and rider updates.</span>
                </div>

            </div>


        </div>


        {{-- Decorative operational card --}}
        <div class="operations-preview">

            <div class="operations-preview-header">

                <span>Operations</span>

                <span class="live-indicator">
                    <i></i>
                    System Ready
                </span>

            </div>


            <div class="operation-flow">

                <div class="operation-step active">

                    <span>01</span>

                    <div>
                        <strong>Receive</strong>
                        <small>Pickup requests</small>
                    </div>

                </div>


                <div class="flow-line"></div>


                <div class="operation-step">

                    <span>02</span>

                    <div>
                        <strong>Sort</strong>
                        <small>Sorting center</small>
                    </div>

                </div>


                <div class="flow-line"></div>


                <div class="operation-step">

                    <span>03</span>

                    <div>
                        <strong>Assign</strong>
                        <small>Rider delivery</small>
                    </div>

                </div>


                <div class="flow-line"></div>


                <div class="operation-step">

                    <span>04</span>

                    <div>
                        <strong>Deliver</strong>
                        <small>Customer</small>
                    </div>

                </div>

            </div>

        </div>


    </section>



    {{-- =====================================================
         LOGIN CARD
         ===================================================== --}}

    <section class="logistics-form-area">


        <div class="logistics-card">


            <div class="card-header">

                <span class="card-eyebrow">
                    SORTING CENTER ACCESS
                </span>

                <h2>
                    Welcome back.
                </h2>

                <p>
                    Sign in to manage your logistics operations.
                </p>

            </div>



            <form
                id="logisticsLoginForm"
                class="logistics-form"
                data-dashboard-url="{{ route('logistics.dashboard') }}"
            >


                {{-- Email --}}
                <div class="form-group">

                    <label for="logisticsEmail">
                        Email address
                    </label>


                    <div class="input-container">

                        <svg
                            class="field-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <rect
                                x="3"
                                y="5"
                                width="18"
                                height="14"
                                rx="2"
                            />
                            <path d="m3 7 9 6 9-6"/>
                        </svg>


                        <input
                            type="email"
                            id="logisticsEmail"
                            placeholder="name@business.com"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>



                {{-- Password --}}
                <div class="form-group">

                    <div class="label-row">

                        <label for="logisticsPassword">
                            Password
                        </label>

                        <a href="#">
                            Forgot password?
                        </a>

                    </div>


                    <div class="input-container">

                        <svg
                            class="field-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <rect
                                x="4"
                                y="10"
                                width="16"
                                height="11"
                                rx="2"
                            />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        </svg>


                        <input
                            type="password"
                            id="logisticsPassword"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-button"
                            data-password-target="logisticsPassword"
                            aria-label="Show password"
                        >

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>

                        </button>

                    </div>

                </div>



                {{-- Remember --}}
                <label class="remember-row">

                    <input
                        type="checkbox"
                        checked
                    >

                    <span>
                        Keep me signed in
                    </span>

                </label>



                {{-- Submit --}}
                <button
                    type="submit"
                    class="primary-button"
                    id="logisticsLoginButton"
                >

                    <span>
                        Sign In to Logistics
                    </span>

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>

                </button>


            </form>



            {{-- Registration --}}
            <div class="registration-callout">

                <div>

                    <strong>
                        New logistics partner?
                    </strong>

                    <span>
                        Register your sorting center with Lumear.
                    </span>

                </div>


                <a
                    href="{{ route('logistics.register') }}"
                    class="secondary-button"
                >
                    Register
                </a>

            </div>



            <div class="secure-note">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M12 3 4 6v5c0 5 3.4 8.8 8 10 4.6-1.2 8-5 8-10V6l-8-3Z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>

                Authorized logistics personnel only.

            </div>


        </div>


    </section>


</div>


@endsection