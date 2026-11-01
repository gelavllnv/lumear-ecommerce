@extends('layouts.logistics-auth')


@section('title', 'Application Submitted — Lumear')


@section('content')


<div class="status-page">


    <div class="status-card">


        <div class="status-icon">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
            >
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7v5l3 2"/>
            </svg>

        </div>


        <span class="status-label">
            APPLICATION SUBMITTED
        </span>


        <h1>
            Your application is
            <span>under review.</span>
        </h1>


        <p class="status-description">

            Thank you for applying as a Lumear logistics partner.
            Your sorting center registration has been submitted
            for administrator verification.

        </p>



        <div class="status-progress">


            <div class="status-step completed">

                <div class="status-step-icon">
                    ✓
                </div>

                <div>

                    <strong>
                        Application submitted
                    </strong>

                    <span>
                        Registration information received
                    </span>

                </div>

            </div>



            <div class="status-line"></div>



            <div class="status-step current">

                <div class="status-step-icon">
                    2
                </div>

                <div>

                    <strong>
                        Administrator review
                    </strong>

                    <span>
                        Identity and business verification
                    </span>

                </div>

            </div>



            <div class="status-line"></div>



            <div class="status-step">

                <div class="status-step-icon">
                    3
                </div>

                <div>

                    <strong>
                        Email notification
                    </strong>

                    <span>
                        Approval result will be sent by email
                    </span>

                </div>

            </div>


        </div>



        <div class="status-message">

            <svg
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


            <div>

                <strong>
                    Check your email
                </strong>

                <span>
                    You will receive an email once the administrator
                    approves or rejects your application.
                </span>

            </div>

        </div>



        <a
            href="{{ route('logistics.login') }}"
            class="return-login-button"
        >

            Return to Logistics Login

        </a>


    </div>


</div>


@endsection