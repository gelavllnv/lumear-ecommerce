
@extends('layouts.logistics')

@section('title', 'Dashboard — Lumear Logistics')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED LOGISTICS DATA
    |--------------------------------------------------------------------------
    |
    | Supplied by LogisticsDashboardController.
    |
    | Statistics are zero until the parcel and rider
    | database relationships have been implemented.
    |
    */

    $businessName = $provider->name ?? 'Logistics Provider';

    $municipality = $provider->municipality_name
        ?? 'Municipality not set';

    $province = $provider->province_name
        ?? 'Province not set';

    $ownerName = $user->name ?? 'Not available';

    $businessEmail = $application->email
        ?? $user->email
        ?? 'Not available';

    $providerStatus = $provider->status ?? 'pending';

    $stats = array_merge([
        'pending_pickups' => 0,
        'incoming_parcels' => 0,
        'sorting_queue' => 0,
        'active_riders' => 0,
        'available_riders' => 0,
        'out_for_delivery' => 0,
        'delivered_today' => 0,
    ], $stats ?? []);

    $flowStages = [
        [
            'label' => 'Pickup Requested',
            'description' => 'Waiting for approval',
            'value' => $stats['pending_pickups'],
            'class' => '',
        ],
        [
            'label' => 'Incoming',
            'description' => 'Moving to center',
            'value' => $stats['incoming_parcels'],
            'class' => '',
        ],
        [
            'label' => 'Sorting',
            'description' => 'At sorting center',
            'value' => $stats['sorting_queue'],
            'class' => 'current',
        ],
        [
            'label' => 'Out for Delivery',
            'description' => 'With assigned riders',
            'value' => $stats['out_for_delivery'],
            'class' => '',
        ],
        [
            'label' => 'Delivered',
            'description' => 'Completed today',
            'value' => $stats['delivered_today'],
            'class' => 'completed',
        ],
    ];
@endphp


{{-- =========================================================
     PAGE HEADER
     ========================================================= --}}

<section class="dashboard-heading">

    <div>

        <span class="page-eyebrow">
            LOGISTICS OPERATIONS
        </span>

        <h1>
            Operations Overview
        </h1>

        <p>
            Monitor parcel flow, riders, pickups,
            sorting operations and deliveries.
        </p>

    </div>

    <div class="heading-actions">

        <a
            href="{{ route('logistics.reports.index') }}"
            class="outline-action-button"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path d="M12 3v12"/>
                <path d="m7 10 5 5 5-5"/>
                <path d="M5 21h14"/>
            </svg>

            View Reports
        </a>

        <a
            href="{{ route('logistics.pickup.index') }}"
            class="primary-action-button"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path d="M12 5v14"/>
                <path d="M5 12h14"/>
            </svg>

            Manage Pickup
        </a>

    </div>

</section>


{{-- =========================================================
     REGISTERED LOGISTICS PROVIDER
     ========================================================= --}}

<section
    class="dashboard-card provider-information-card"
    style="margin-bottom:24px;"
>

    <div class="card-heading">

        <div>

            <span class="card-eyebrow">
                REGISTERED LOGISTICS PROVIDER
            </span>

            <h2>
                {{ $businessName }}
            </h2>

            <p>
                {{ $municipality }},
                {{ $province }}
            </p>

        </div>

        <span class="stat-change positive">
            {{ ucfirst($providerStatus) }}
        </span>

    </div>

    <div
        style="
            display:grid;
            grid-template-columns:repeat(
                auto-fit,
                minmax(180px,1fr)
            );
            gap:22px;
            padding:0 22px 24px;
        "
    >

        <div>

            <span class="stat-label">
                Business Owner
            </span>

            <strong
                style="
                    display:block;
                    margin-top:8px;
                    overflow-wrap:anywhere;
                "
            >
                {{ $ownerName }}
            </strong>

        </div>

        <div>

            <span class="stat-label">
                Registered Email
            </span>

            <strong
                style="
                    display:block;
                    margin-top:8px;
                    overflow-wrap:anywhere;
                "
            >
                {{ $businessEmail }}
            </strong>

        </div>

        <div>

            <span class="stat-label">
                Assigned Municipality
            </span>

            <strong
                style="
                    display:block;
                    margin-top:8px;
                "
            >
                {{ $municipality }}
            </strong>

        </div>

        <div>

            <span class="stat-label">
                Municipality PSGC Code
            </span>

            <strong
                style="
                    display:block;
                    margin-top:8px;
                "
            >
                {{ $provider->municipality_psgc_code
                    ?? 'Not available' }}
            </strong>

        </div>

    </div>

</section>


{{-- =========================================================
     KPI STATISTICS
     ========================================================= --}}

<section class="stats-grid">


    {{-- PENDING PICKUP REQUESTS --}}

    <article class="stat-card">

        <div class="stat-card-top">

            <div class="stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    <path d="m3.3 7 8.7 5 8.7-5"/>
                </svg>
            </div>

            <span class="stat-change attention">
                Needs action
            </span>

        </div>

        <span class="stat-label">
            Pending Pickup Requests
        </span>

        <strong class="stat-number">
            {{ number_format($stats['pending_pickups']) }}
        </strong>

        <p>
            Requests awaiting processing
        </p>

    </article>


    {{-- INCOMING PARCELS --}}

    <article class="stat-card">

        <div class="stat-card-top">

            <div class="stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M4 7h16v13H4z"/>
                    <path d="M8 7V4h8v3"/>
                    <path d="M8 12h8"/>
                </svg>
            </div>

            <span class="stat-change">
                Incoming
            </span>

        </div>

        <span class="stat-label">
            Incoming Parcels
        </span>

        <strong class="stat-number">
            {{ number_format($stats['incoming_parcels']) }}
        </strong>

        <p>
            Expected at the sorting center
        </p>

    </article>


    {{-- SORTING QUEUE --}}

    <article class="stat-card">

        <div class="stat-card-top">

            <div class="stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M4 5h16"/>
                    <path d="M7 10h10"/>
                    <path d="M10 15h4"/>
                    <path d="M12 15v5"/>
                </svg>
            </div>

            <span class="stat-change">
                Processing
            </span>

        </div>

        <span class="stat-label">
            Sorting Queue
        </span>

        <strong class="stat-number">
            {{ number_format($stats['sorting_queue']) }}
        </strong>

        <p>
            Parcels waiting to be sorted
        </p>

    </article>


    {{-- ACTIVE RIDERS --}}

    <article class="stat-card">

        <div class="stat-card-top">

            <div class="stat-icon">
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
                </svg>
            </div>

            <span class="stat-change positive">
                {{ $stats['available_riders'] }} available
            </span>

        </div>

        <span class="stat-label">
            Active Riders
        </span>

        <strong class="stat-number">
            {{ number_format($stats['active_riders']) }}
        </strong>

        <p>
            Approved and currently active
        </p>

    </article>


    {{-- OUT FOR DELIVERY --}}

    <article class="stat-card">

        <div class="stat-card-top">

            <div class="stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="7" cy="17" r="2"/>
                    <circle cx="17" cy="17" r="2"/>
                    <path d="M5 17H3V6h11v11H9"/>
                    <path d="M14 10h4l3 3v4h-2"/>
                </svg>
            </div>

            <span class="stat-change">
                Delivery
            </span>

        </div>

        <span class="stat-label">
            Out for Delivery
        </span>

        <strong class="stat-number">
            {{ number_format($stats['out_for_delivery']) }}
        </strong>

        <p>
            Currently assigned to riders
        </p>

    </article>


    {{-- DELIVERED TODAY --}}

    <article class="stat-card">

        <div class="stat-card-top">

            <div class="stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="12" cy="12" r="9"/>
                    <path d="m8 12 3 3 5-6"/>
                </svg>
            </div>

            <span class="stat-change positive">
                Completed
            </span>

        </div>

        <span class="stat-label">
            Delivered Today
        </span>

        <strong class="stat-number">
            {{ number_format($stats['delivered_today']) }}
        </strong>

        <p>
            Successfully completed deliveries
        </p>

    </article>

</section>


{{-- =========================================================
     PARCEL FLOW
     ========================================================= --}}

<section class="dashboard-card parcel-flow-card">

    <div class="card-heading">

        <div>

            <span class="card-eyebrow">
                OPERATIONS WORKFLOW
            </span>

            <h2>
                Parcel Flow
            </h2>

            <p>
                Current parcel distribution across
                each logistics stage.
            </p>

        </div>

        <a href="{{ route('logistics.pickup.index') }}">
            View operations
        </a>

    </div>

    <div class="parcel-flow">

        @foreach($flowStages as $stage)

            @if(!$loop->first)

                <div class="flow-connector">

                    <div class="connector-line"></div>

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="m9 18 6-6-6-6"/>
                    </svg>

                </div>

            @endif

            <div class="flow-stage {{ $stage['class'] }}">

                <div class="flow-number">
                    {{ number_format($stage['value']) }}
                </div>

                <strong>
                    {{ $stage['label'] }}
                </strong>

                <span>
                    {{ $stage['description'] }}
                </span>

            </div>

        @endforeach

    </div>

</section>


{{-- =========================================================
     ANALYTICS AND DELIVERY PERFORMANCE
     ========================================================= --}}

<section class="analytics-grid">


    {{-- PARCEL ACTIVITY --}}

    <article class="dashboard-card analytics-chart-card">

        <div class="card-heading">

            <div>

                <span class="card-eyebrow">
                    7-DAY ANALYTICS
                </span>

                <h2>
                    Parcel Activity
                </h2>

                <p>
                    Incoming and successfully delivered
                    parcels this week.
                </p>

            </div>

            <div class="analytics-period">
                Last 7 days
            </div>

        </div>

        <div class="chart-summary">

            <div class="chart-summary-item">

                <span
                    class="chart-summary-dot incoming-dot"
                ></span>

                <div>
                    <span>Incoming</span>
                    <strong>—</strong>
                </div>

            </div>

            <div class="chart-summary-item">

                <span
                    class="chart-summary-dot delivered-dot"
                ></span>

                <div>
                    <span>Delivered</span>
                    <strong>—</strong>
                </div>

            </div>

            <div class="chart-trend">

                <span>
                    Weekly throughput
                </span>

                <strong>
                    —
                </strong>

            </div>

        </div>

        <div class="chart-container">

            <div
                style="
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    min-height:220px;
                    padding:24px;
                    text-align:center;
                "
            >

                <p>
                    Parcel activity will appear here
                    once order and delivery records
                    are connected to this Logistics provider.
                </p>

            </div>

        </div>

    </article>


    {{-- DELIVERY PERFORMANCE --}}

    <article class="dashboard-card performance-card">

        <div class="card-heading">

            <div>

                <span class="card-eyebrow">
                    DELIVERY QUALITY
                </span>

                <h2>
                    Delivery Performance
                </h2>

                <p>
                    Current delivery completion status.
                </p>

            </div>

        </div>

        <div class="performance-content">

            <div class="success-rate">

                <div class="success-ring">

                    <svg viewBox="0 0 120 120">

                        <circle
                            class="ring-background"
                            cx="60"
                            cy="60"
                            r="50"
                        />

                        <circle
                            class="ring-progress"
                            cx="60"
                            cy="60"
                            r="50"
                            style="
                                stroke-dasharray:314.16;
                                stroke-dashoffset:314.16;
                            "
                        />

                    </svg>

                    <div class="success-ring-content">

                        <strong>
                            —
                        </strong>

                        <span>
                            No data
                        </span>

                    </div>

                </div>

                <div class="success-copy">

                    <span>
                        DELIVERY SUCCESS RATE
                    </span>

                    <strong>
                        Awaiting delivery records
                    </strong>

                    <p>
                        Delivery performance will be
                        calculated from completed and
                        unsuccessful delivery records.
                    </p>

                </div>

            </div>

            <div class="performance-list">

                <div class="performance-row">

                    <div>

                        <span
                            class="performance-indicator delivered"
                        ></span>

                        <span>
                            Delivered
                        </span>

                    </div>

                    <strong>—</strong>

                </div>

                <div class="performance-row">

                    <div>

                        <span
                            class="performance-indicator pending"
                        ></span>

                        <span>
                            Pending
                        </span>

                    </div>

                    <strong>—</strong>

                </div>

                <div class="performance-row">

                    <div>

                        <span
                            class="performance-indicator failed"
                        ></span>

                        <span>
                            Failed
                        </span>

                    </div>

                    <strong>—</strong>

                </div>

            </div>

            <div class="performance-footer">

                <div>

                    <span>
                        VS. PREVIOUS WEEK
                    </span>

                    <strong>
                        —
                    </strong>

                </div>

                <p>
                    Weekly comparisons will become
                    available when delivery records
                    are connected.
                </p>

            </div>

        </div>

    </article>

</section>


{{-- =========================================================
     RECENT PICKUPS AND RIDERS
     ========================================================= --}}

<div class="dashboard-grid">


    {{-- RECENT PICKUP REQUESTS --}}

    <section class="dashboard-card pickup-card">

        <div class="card-heading">

            <div>

                <span class="card-eyebrow">
                    SELLER REQUESTS
                </span>

                <h2>
                    Recent Pickup Requests
                </h2>

            </div>

            <a href="{{ route('logistics.pickup.index') }}">
                View all
            </a>

        </div>

        <div class="table-wrapper">

            <table class="operations-table">

                <thead>

                    <tr>
                        <th>Request</th>
                        <th>Seller</th>
                        <th>Parcels</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <tr>

                        <td
                            colspan="6"
                            style="
                                padding:35px 20px;
                                text-align:center;
                            "
                        >

                            <strong>
                                No pickup records connected yet
                            </strong>

                            <p style="margin-top:8px;">
                                Pickup requests will appear
                                here once the pickup module
                                is connected to MySQL.
                            </p>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </section>


    {{-- RIDER AVAILABILITY --}}

    <section class="dashboard-card rider-card">

        <div class="card-heading">

            <div>

                <span class="card-eyebrow">
                    RIDER NETWORK
                </span>

                <h2>
                    Rider Availability
                </h2>

            </div>

            <a href="{{ route('logistics.riders.index') }}">
                Manage
            </a>

        </div>

        <div class="availability-summary">

            <div>

                <strong>
                    {{ $stats['available_riders'] }}
                </strong>

                <span>
                    Available
                </span>

            </div>

            <div>

                <strong>
                    —
                </strong>

                <span>
                    On delivery
                </span>

            </div>

            <div>

                <strong>
                    {{ $stats['active_riders'] }}
                </strong>

                <span>
                    Active
                </span>

            </div>

        </div>

        <div class="rider-list">

            <div
                style="
                    padding:24px;
                    text-align:center;
                "
            >

                <strong>
                    No rider records connected yet
                </strong>

                <p style="margin-top:8px;">
                    Registered riders and their
                    barangay assignments will appear
                    once Rider Management is connected.
                </p>

            </div>

        </div>

    </section>

</div>


{{-- =========================================================
     DELIVERY ACTIVITY AND QUICK ACTIONS
     ========================================================= --}}

<div class="dashboard-bottom-grid">


    {{-- DELIVERY ACTIVITY --}}

    <section class="dashboard-card activity-card">

        <div class="card-heading">

            <div>

                <span class="card-eyebrow">
                    LIVE OPERATIONS
                </span>

                <h2>
                    Delivery Activity
                </h2>

            </div>

            <a href="{{ route('logistics.monitoring.index') }}">
                Monitor all
            </a>

        </div>

        <div class="activity-list">

            <div
                style="
                    padding:24px;
                    text-align:center;
                "
            >

                <strong>
                    No delivery activity connected yet
                </strong>

                <p style="margin-top:8px;">
                    Rider pickup, parcel movement,
                    and delivery events will appear
                    after the delivery backend
                    is connected.
                </p>

            </div>

        </div>

    </section>


    {{-- QUICK ACTIONS --}}

    <section class="dashboard-card quick-actions-card">

        <div class="card-heading">

            <div>

                <span class="card-eyebrow">
                    SHORTCUTS
                </span>

                <h2>
                    Quick Actions
                </h2>

            </div>

        </div>

        <div class="quick-actions-grid">


            <a href="{{ route('logistics.pickup.index') }}">

                <span class="quick-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    </svg>

                </span>

                <div>

                    <strong>
                        Review Pickups
                    </strong>

                    <span>
                        {{ $stats['pending_pickups'] }}
                        pending requests
                    </span>

                </div>

            </a>


            <a href="{{ route('logistics.riders.applications') }}">

                <span class="quick-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M3 21v-2a6 6 0 0 1 12 0v2"/>
                    </svg>

                </span>

                <div>

                    <strong>
                        Rider Applications
                    </strong>

                    <span>
                        Review registered riders
                    </span>

                </div>

            </a>


            <a href="{{ route('logistics.assignment.index') }}">

                <span class="quick-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="7" cy="17" r="2"/>
                        <circle cx="17" cy="17" r="2"/>
                        <path d="M5 17H3V6h11v11H9"/>
                        <path d="M14 10h4l3 3v4h-2"/>
                    </svg>

                </span>

                <div>

                    <strong>
                        Assign Delivery
                    </strong>

                    <span>
                        Match parcels to riders
                    </span>

                </div>

            </a>


            <a href="{{ route('logistics.reports.index') }}">

                <span class="quick-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M4 20V10"/>
                        <path d="M10 20V4"/>
                        <path d="M16 20v-7"/>
                        <path d="M22 20H2"/>
                    </svg>

                </span>

                <div>

                    <strong>
                        Generate Report
                    </strong>

                    <span>
                        View operations summary
                    </span>

                </div>

            </a>

        </div>

    </section>

</div>


{{-- =========================================================
     BACKEND STATUS NOTICE
     ========================================================= --}}

<div class="development-notice">

    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
    >
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 11v5"/>
        <path d="M12 8h.01"/>
    </svg>

    <div>

        <strong>
            Backend Integration in Progress
        </strong>

        <span>
            Logistics provider information is retrieved
            from the MySQL database. Parcel, pickup,
            sorting, rider, and delivery statistics are
            not yet connected to operational records.
            Zero values are placeholders until those
            modules are implemented.
        </span>

    </div>

</div>

@endsection
