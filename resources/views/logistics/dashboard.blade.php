@extends('layouts.logistics')


@section('title', 'Dashboard — Lumear Logistics')


@section('content')


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
            Monitor today's parcel flow, riders, pickups,
            sorting operations and deliveries.
        </p>

    </div>


    <div class="heading-actions">

        <button
            type="button"
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

            Export Summary

        </button>


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
     KPI CARDS
     ========================================================= --}}

<section class="stats-grid">


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
            8
        </strong>

        <p>
            3 received within the last hour
        </p>

    </article>



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
                Today
            </span>

        </div>

        <span class="stat-label">
            Incoming Parcels
        </span>

        <strong class="stat-number">
            34
        </strong>

        <p>
            Expected at the sorting center
        </p>

    </article>



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
            21
        </strong>

        <p>
            Parcels waiting to be sorted
        </p>

    </article>



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
                7 available
            </span>

        </div>

        <span class="stat-label">
            Active Riders
        </span>

        <strong class="stat-number">
            12
        </strong>

        <p>
            Approved and currently active
        </p>

    </article>



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
                Live
            </span>

        </div>

        <span class="stat-label">
            Out for Delivery
        </span>

        <strong class="stat-number">
            18
        </strong>

        <p>
            Currently assigned to riders
        </p>

    </article>



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
                +14%
            </span>

        </div>

        <span class="stat-label">
            Delivered Today
        </span>

        <strong class="stat-number">
            46
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
                TODAY'S WORKFLOW
            </span>

            <h2>
                Parcel Flow
            </h2>

            <p>
                Current parcel distribution across each logistics stage.
            </p>

        </div>

        <a href="{{ route('logistics.pickup.index') }}">
            View operations
        </a>

    </div>


    <div class="parcel-flow">


        <div class="flow-stage">

            <div class="flow-number">
                8
            </div>

            <strong>
                Pickup Requested
            </strong>

            <span>
                Waiting for approval
            </span>

        </div>


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


        <div class="flow-stage">

            <div class="flow-number">
                34
            </div>

            <strong>
                Incoming
            </strong>

            <span>
                Moving to center
            </span>

        </div>


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


        <div class="flow-stage current">

            <div class="flow-number">
                21
            </div>

            <strong>
                Sorting
            </strong>

            <span>
                At sorting center
            </span>

        </div>


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


        <div class="flow-stage">

            <div class="flow-number">
                18
            </div>

            <strong>
                Out for Delivery
            </strong>

            <span>
                With assigned riders
            </span>

        </div>


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


        <div class="flow-stage completed">

            <div class="flow-number">
                46
            </div>

            <strong>
                Delivered
            </strong>

            <span>
                Completed today
            </span>

        </div>


    </div>

</section>



{{-- =========================================================
     NEW: ANALYTICS / STATISTICS
     ========================================================= --}}

<section class="analytics-grid">


    {{-- Parcel activity graph --}}
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
                    Incoming and successfully delivered parcels this week.
                </p>

            </div>


            <div class="analytics-period">

                <span class="live-pulse"></span>

                Last 7 days

            </div>

        </div>


        <div class="chart-summary">

            <div class="chart-summary-item">

                <span class="chart-summary-dot incoming-dot"></span>

                <div>

                    <span>
                        Incoming
                    </span>

                    <strong>
                        185
                    </strong>

                </div>

            </div>


            <div class="chart-summary-item">

                <span class="chart-summary-dot delivered-dot"></span>

                <div>

                    <span>
                        Delivered
                    </span>

                    <strong>
                        158
                    </strong>

                </div>

            </div>


            <div class="chart-trend">

                <span>
                    Weekly throughput
                </span>

                <strong>
                    +8.6%
                </strong>

            </div>

        </div>


        <div class="chart-container">

            <canvas
                id="parcelActivityChart"
                aria-label="Parcel activity for the last seven days"
            ></canvas>

        </div>

    </article>



    {{-- Delivery performance --}}
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
                        />

                    </svg>


                    <div class="success-ring-content">

                        <strong>
                            94%
                        </strong>

                        <span>
                            Success
                        </span>

                    </div>

                </div>


                <div class="success-copy">

                    <span>
                        DELIVERY SUCCESS RATE
                    </span>

                    <strong>
                        Excellent performance
                    </strong>

                    <p>
                        Most assigned parcels are being delivered
                        successfully.
                    </p>

                </div>

            </div>



            <div class="performance-list">


                <div class="performance-row">

                    <div>

                        <span class="performance-indicator delivered"></span>

                        <span>
                            Delivered
                        </span>

                    </div>

                    <strong>
                        126
                    </strong>

                </div>


                <div class="performance-row">

                    <div>

                        <span class="performance-indicator pending"></span>

                        <span>
                            Pending
                        </span>

                    </div>

                    <strong>
                        7
                    </strong>

                </div>


                <div class="performance-row">

                    <div>

                        <span class="performance-indicator failed"></span>

                        <span>
                            Failed
                        </span>

                    </div>

                    <strong>
                        4
                    </strong>

                </div>


            </div>



            <div class="performance-footer">

                <div>

                    <span>
                        VS. PREVIOUS WEEK
                    </span>

                    <strong>
                        ↑ 6.4%
                    </strong>

                </div>

                <p>
                    Delivery success improved this week.
                </p>

            </div>


        </div>

    </article>


</section>



{{-- =========================================================
     RECENT PICKUPS + RIDERS
     ========================================================= --}}

<div class="dashboard-grid">


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

                        <td>

                            <strong class="table-id">
                                PU-1028
                            </strong>

                            <span class="table-subtext">
                                10:42 AM
                            </span>

                        </td>


                        <td>

                            <div class="seller-cell">

                                <span class="seller-avatar">
                                    MA
                                </span>

                                <div>

                                    <strong>
                                        Maven Apparel
                                    </strong>

                                    <span>
                                        Seller
                                    </span>

                                </div>

                            </div>

                        </td>


                        <td>
                            4
                        </td>

                        <td>
                            Santa Cruz
                        </td>

                        <td>

                            <span class="status-badge pending">
                                Pending
                            </span>

                        </td>

                        <td>

                            <button
                                type="button"
                                class="table-action-button"
                            >
                                Review
                            </button>

                        </td>

                    </tr>



                    <tr>

                        <td>

                            <strong class="table-id">
                                PU-1027
                            </strong>

                            <span class="table-subtext">
                                10:18 AM
                            </span>

                        </td>


                        <td>

                            <div class="seller-cell">

                                <span class="seller-avatar">
                                    HS
                                </span>

                                <div>

                                    <strong>
                                        Home Studio
                                    </strong>

                                    <span>
                                        Seller
                                    </span>

                                </div>

                            </div>

                        </td>

                        <td>
                            7
                        </td>

                        <td>
                            Pila
                        </td>

                        <td>

                            <span class="status-badge approved">
                                Approved
                            </span>

                        </td>

                        <td>

                            <button
                                type="button"
                                class="table-action-button"
                            >
                                Details
                            </button>

                        </td>

                    </tr>



                    <tr>

                        <td>

                            <strong class="table-id">
                                PU-1026
                            </strong>

                            <span class="table-subtext">
                                9:56 AM
                            </span>

                        </td>


                        <td>

                            <div class="seller-cell">

                                <span class="seller-avatar">
                                    GC
                                </span>

                                <div>

                                    <strong>
                                        Galleria Crafts
                                    </strong>

                                    <span>
                                        Seller
                                    </span>

                                </div>

                            </div>

                        </td>

                        <td>
                            2
                        </td>

                        <td>
                            Victoria
                        </td>

                        <td>

                            <span class="status-badge assigned">
                                Assigned
                            </span>

                        </td>

                        <td>

                            <button
                                type="button"
                                class="table-action-button"
                            >
                                Track
                            </button>

                        </td>

                    </tr>


                </tbody>

            </table>

        </div>

    </section>



    {{-- Rider availability --}}
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

            <a href="#">
                Manage
            </a>

        </div>


        <div class="availability-summary">

            <div>

                <strong>
                    7
                </strong>

                <span>
                    Available
                </span>

            </div>


            <div>

                <strong>
                    5
                </strong>

                <span>
                    On delivery
                </span>

            </div>


            <div>

                <strong>
                    12
                </strong>

                <span>
                    Active
                </span>

            </div>

        </div>


        <div class="rider-list">


            <div class="rider-row">

                <div class="rider-avatar">
                    JR
                </div>

                <div class="rider-details">

                    <strong>
                        Juan Reyes
                    </strong>

                    <span>
                        Santa Cruz Area
                    </span>

                </div>

                <span class="rider-status available">
                    Available
                </span>

            </div>


            <div class="rider-row">

                <div class="rider-avatar">
                    MC
                </div>

                <div class="rider-details">

                    <strong>
                        Miguel Cruz
                    </strong>

                    <span>
                        Pila Area
                    </span>

                </div>

                <span class="rider-status delivering">
                    Delivering
                </span>

            </div>


            <div class="rider-row">

                <div class="rider-avatar">
                    AS
                </div>

                <div class="rider-details">

                    <strong>
                        Andrea Santos
                    </strong>

                    <span>
                        Victoria Area
                    </span>

                </div>

                <span class="rider-status available">
                    Available
                </span>

            </div>


            <div class="rider-row">

                <div class="rider-avatar">
                    DP
                </div>

                <div class="rider-details">

                    <strong>
                        Daniel Perez
                    </strong>

                    <span>
                        Pagsanjan Area
                    </span>

                </div>

                <span class="rider-status delivering">
                    Delivering
                </span>

            </div>


        </div>

    </section>


</div>



{{-- =========================================================
     BOTTOM
     ========================================================= --}}

<div class="dashboard-bottom-grid">


    {{-- Delivery activity --}}
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

            <a href="#">
                Monitor all
            </a>

        </div>


        <div class="activity-list">


            <div class="activity-entry">

                <div class="activity-marker delivered">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="m7 12 3 3 7-7"/>
                    </svg>

                </div>


                <div class="activity-content">

                    <strong>
                        Parcel LMR-2418 delivered
                    </strong>

                    <p>
                        Juan Reyes completed the delivery in Santa Cruz.
                    </p>

                    <span>
                        6 minutes ago
                    </span>

                </div>

            </div>



            <div class="activity-entry">

                <div class="activity-marker moving">

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

                </div>


                <div class="activity-content">

                    <strong>
                        3 parcels out for delivery
                    </strong>

                    <p>
                        Miguel Cruz started a delivery route in Pila.
                    </p>

                    <span>
                        19 minutes ago
                    </span>

                </div>

            </div>



            <div class="activity-entry">

                <div class="activity-marker sorting">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M4 6h16"/>
                        <path d="M7 12h10"/>
                        <path d="M10 18h4"/>
                    </svg>

                </div>


                <div class="activity-content">

                    <strong>
                        Batch SC-084 entered sorting
                    </strong>

                    <p>
                        8 parcels were received at Santa Cruz Hub.
                    </p>

                    <span>
                        34 minutes ago
                    </span>

                </div>

            </div>


        </div>

    </section>



    {{-- Quick actions --}}
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


            <a href="#">

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
                        8 pending requests
                    </span>

                </div>

            </a>



            <a href="#">

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
                        3 waiting for review
                    </span>

                </div>

            </a>



            <a href="#">

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



            <a href="#">

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
                        Create operations summary
                    </span>

                </div>

            </a>


        </div>

    </section>


</div>



{{-- =========================================================
     DEVELOPMENT NOTICE
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
            Development Mode
        </strong>

        <span>
            Dashboard statistics and analytics currently use demonstration
            data. These values will come from actual parcel, pickup,
            delivery and rider records once the database modules are connected.
        </span>

    </div>

</div>


{{-- =========================================================
     CHART.JS

     CDN only.
     No npm / Vite required.
     ========================================================= --}}

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>


@endsection