@extends('layouts.logistics')

@section('title', 'Pickup Requests — Lumear Logistics')

@section('content')

<link
    rel="stylesheet"
    href="{{ asset('css/logistics/pickup.css') }}"
>


{{-- =========================================================
     PAGE HEADER
     ========================================================= --}}

<section class="pickup-page-header">

    <div>

        <span class="pickup-eyebrow">
            PARCEL OPERATIONS
        </span>

        <h1>
            Pickup Requests
        </h1>

        <p>
            Review seller pickup requests, approve parcels,
            and assign available riders for collection.
        </p>

    </div>

    <div class="pickup-header-actions">

        <button
            type="button"
            class="pickup-secondary-button"
            id="refreshPickupButton"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path d="M20 6v6h-6"/>
                <path d="M4 18v-6h6"/>
                <path d="M6.5 8a7 7 0 0 1 11.7-2L20 8"/>
                <path d="M4 16l1.8 2A7 7 0 0 0 17.5 16"/>
            </svg>

            Refresh
        </button>

    </div>

</section>


{{-- =========================================================
     STATISTICS
     ========================================================= --}}

<section class="pickup-statistics">

    <article class="pickup-stat-card">

        <div class="pickup-stat-icon pending">

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

            <span>
                Pending Review
            </span>

            <strong id="pendingCount">
                3
            </strong>

            <small>
                Requires logistics action
            </small>

        </div>

    </article>


    <article class="pickup-stat-card">

        <div class="pickup-stat-icon approved">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path d="m7 12 3 3 7-7"/>
                <circle cx="12" cy="12" r="9"/>
            </svg>

        </div>

        <div>

            <span>
                Approved
            </span>

            <strong id="approvedCount">
                2
            </strong>

            <small>
                Ready for rider assignment
            </small>

        </div>

    </article>


    <article class="pickup-stat-card">

        <div class="pickup-stat-icon assigned">

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

        <div>

            <span>
                Rider Assigned
            </span>

            <strong id="assignedCount">
                2
            </strong>

            <small>
                Waiting for collection
            </small>

        </div>

    </article>


    <article class="pickup-stat-card">

        <div class="pickup-stat-icon completed">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                <path d="m8 12 2.5 2.5L16 9"/>
            </svg>

        </div>

        <div>

            <span>
                Picked Up Today
            </span>

            <strong>
                14
            </strong>

            <small>
                Successfully collected
            </small>

        </div>

    </article>

</section>


{{-- =========================================================
     FILTERS
     ========================================================= --}}

<section class="pickup-panel pickup-filter-panel">

    <div class="pickup-search">

        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
        >
            <circle cx="11" cy="11" r="7"/>
            <path d="m20 20-4-4"/>
        </svg>

        <input
            type="search"
            id="pickupSearch"
            placeholder="Search request ID, seller, area..."
        >

    </div>


    <div class="pickup-filters">

        <select id="pickupStatusFilter">

            <option value="all">
                All Status
            </option>

            <option value="pending">
                Pending
            </option>

            <option value="approved">
                Approved
            </option>

            <option value="assigned">
                Assigned
            </option>

            <option value="rejected">
                Rejected
            </option>

        </select>


        <select id="pickupAreaFilter">

            <option value="all">
                All Areas
            </option>

            <option value="santa cruz">
                Santa Cruz
            </option>

            <option value="pila">
                Pila
            </option>

            <option value="victoria">
                Victoria
            </option>

            <option value="pagsanjan">
                Pagsanjan
            </option>

        </select>

    </div>

</section>


{{-- =========================================================
     REQUEST TABLE
     ========================================================= --}}

<section class="pickup-panel">

    <div class="pickup-panel-heading">

        <div>

            <span class="pickup-eyebrow">
                SELLER REQUESTS
            </span>

            <h2>
                Pickup Queue
            </h2>

            <p>
                Requests submitted by sellers for parcel collection.
            </p>

        </div>

        <span class="pickup-result-count">
            <strong id="visibleRequestCount">
                7
            </strong>
            requests
        </span>

    </div>


    <div class="pickup-table-wrapper">

        <table class="pickup-table">

            <thead>

                <tr>
                    <th>Request</th>
                    <th>Seller</th>
                    <th>Pickup Address</th>
                    <th>Parcels</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Rider</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody id="pickupTableBody">


                {{-- REQUEST 1 --}}
                <tr
                    class="pickup-request-row"
                    data-id="PU-1028"
                    data-seller="Maven Apparel"
                    data-area="Santa Cruz"
                    data-status="pending"
                >

                    <td>

                        <strong class="request-id">
                            PU-1028
                        </strong>

                        <span class="request-reference">
                            LMR-PU-241028
                        </span>

                    </td>


                    <td>

                        <div class="seller-information">

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

                        <strong class="address-main">
                            Santa Cruz, Laguna
                        </strong>

                        <span class="address-secondary">
                            Brgy. Bubukal
                        </span>

                    </td>


                    <td>
                        <strong>4</strong>
                    </td>


                    <td>

                        <strong>
                            Today
                        </strong>

                        <span class="request-time">
                            10:42 AM
                        </span>

                    </td>


                    <td>

                        <span class="pickup-status pending">
                            Pending
                        </span>

                    </td>


                    <td>

                        <span class="unassigned-text">
                            Not assigned
                        </span>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="review-request-button"
                            data-request="PU-1028"
                        >
                            Review
                        </button>

                    </td>

                </tr>


                {{-- REQUEST 2 --}}
                <tr
                    class="pickup-request-row"
                    data-id="PU-1027"
                    data-seller="Home Studio"
                    data-area="Pila"
                    data-status="approved"
                >

                    <td>

                        <strong class="request-id">
                            PU-1027
                        </strong>

                        <span class="request-reference">
                            LMR-PU-241027
                        </span>

                    </td>


                    <td>

                        <div class="seller-information">

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

                        <strong class="address-main">
                            Pila, Laguna
                        </strong>

                        <span class="address-secondary">
                            Brgy. Bulilan Sur
                        </span>

                    </td>


                    <td>
                        <strong>7</strong>
                    </td>


                    <td>

                        <strong>
                            Today
                        </strong>

                        <span class="request-time">
                            10:18 AM
                        </span>

                    </td>


                    <td>

                        <span class="pickup-status approved">
                            Approved
                        </span>

                    </td>


                    <td>

                        <span class="unassigned-text">
                            Not assigned
                        </span>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="review-request-button"
                            data-request="PU-1027"
                        >
                            Assign
                        </button>

                    </td>

                </tr>


                {{-- REQUEST 3 --}}
                <tr
                    class="pickup-request-row"
                    data-id="PU-1026"
                    data-seller="Galleria Crafts"
                    data-area="Victoria"
                    data-status="assigned"
                >

                    <td>

                        <strong class="request-id">
                            PU-1026
                        </strong>

                        <span class="request-reference">
                            LMR-PU-241026
                        </span>

                    </td>


                    <td>

                        <div class="seller-information">

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

                        <strong class="address-main">
                            Victoria, Laguna
                        </strong>

                        <span class="address-secondary">
                            Brgy. Masapang
                        </span>

                    </td>


                    <td>
                        <strong>2</strong>
                    </td>


                    <td>

                        <strong>
                            Today
                        </strong>

                        <span class="request-time">
                            9:56 AM
                        </span>

                    </td>


                    <td>

                        <span class="pickup-status assigned">
                            Assigned
                        </span>

                    </td>


                    <td>

                        <div class="assigned-rider">

                            <span>
                                JR
                            </span>

                            <strong>
                                Juan Reyes
                            </strong>

                        </div>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="review-request-button"
                            data-request="PU-1026"
                        >
                            Details
                        </button>

                    </td>

                </tr>


                {{-- REQUEST 4 --}}
                <tr
                    class="pickup-request-row"
                    data-id="PU-1025"
                    data-seller="Luna Essentials"
                    data-area="Santa Cruz"
                    data-status="pending"
                >

                    <td>

                        <strong class="request-id">
                            PU-1025
                        </strong>

                        <span class="request-reference">
                            LMR-PU-241025
                        </span>

                    </td>


                    <td>

                        <div class="seller-information">

                            <span class="seller-avatar">
                                LE
                            </span>

                            <div>

                                <strong>
                                    Luna Essentials
                                </strong>

                                <span>
                                    Seller
                                </span>

                            </div>

                        </div>

                    </td>


                    <td>

                        <strong class="address-main">
                            Santa Cruz, Laguna
                        </strong>

                        <span class="address-secondary">
                            Brgy. Patimbao
                        </span>

                    </td>


                    <td>
                        <strong>5</strong>
                    </td>


                    <td>

                        <strong>
                            Today
                        </strong>

                        <span class="request-time">
                            9:31 AM
                        </span>

                    </td>


                    <td>

                        <span class="pickup-status pending">
                            Pending
                        </span>

                    </td>


                    <td>

                        <span class="unassigned-text">
                            Not assigned
                        </span>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="review-request-button"
                            data-request="PU-1025"
                        >
                            Review
                        </button>

                    </td>

                </tr>


                {{-- REQUEST 5 --}}
                <tr
                    class="pickup-request-row"
                    data-id="PU-1024"
                    data-seller="North & Co."
                    data-area="Pagsanjan"
                    data-status="approved"
                >

                    <td>

                        <strong class="request-id">
                            PU-1024
                        </strong>

                        <span class="request-reference">
                            LMR-PU-241024
                        </span>

                    </td>


                    <td>

                        <div class="seller-information">

                            <span class="seller-avatar">
                                NC
                            </span>

                            <div>

                                <strong>
                                    North & Co.
                                </strong>

                                <span>
                                    Seller
                                </span>

                            </div>

                        </div>

                    </td>


                    <td>

                        <strong class="address-main">
                            Pagsanjan, Laguna
                        </strong>

                        <span class="address-secondary">
                            Brgy. Sampaloc
                        </span>

                    </td>


                    <td>
                        <strong>3</strong>
                    </td>


                    <td>

                        <strong>
                            Today
                        </strong>

                        <span class="request-time">
                            8:48 AM
                        </span>

                    </td>


                    <td>

                        <span class="pickup-status approved">
                            Approved
                        </span>

                    </td>


                    <td>

                        <span class="unassigned-text">
                            Not assigned
                        </span>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="review-request-button"
                            data-request="PU-1024"
                        >
                            Assign
                        </button>

                    </td>

                </tr>


                {{-- REQUEST 6 --}}
                <tr
                    class="pickup-request-row"
                    data-id="PU-1023"
                    data-seller="Crafted Daily"
                    data-area="Pila"
                    data-status="assigned"
                >

                    <td>

                        <strong class="request-id">
                            PU-1023
                        </strong>

                        <span class="request-reference">
                            LMR-PU-241023
                        </span>

                    </td>


                    <td>

                        <div class="seller-information">

                            <span class="seller-avatar">
                                CD
                            </span>

                            <div>

                                <strong>
                                    Crafted Daily
                                </strong>

                                <span>
                                    Seller
                                </span>

                            </div>

                        </div>

                    </td>


                    <td>

                        <strong class="address-main">
                            Pila, Laguna
                        </strong>

                        <span class="address-secondary">
                            Brgy. Aplaya
                        </span>

                    </td>


                    <td>
                        <strong>6</strong>
                    </td>


                    <td>

                        <strong>
                            Yesterday
                        </strong>

                        <span class="request-time">
                            4:22 PM
                        </span>

                    </td>


                    <td>

                        <span class="pickup-status assigned">
                            Assigned
                        </span>

                    </td>


                    <td>

                        <div class="assigned-rider">

                            <span>
                                MC
                            </span>

                            <strong>
                                Miguel Cruz
                            </strong>

                        </div>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="review-request-button"
                            data-request="PU-1023"
                        >
                            Details
                        </button>

                    </td>

                </tr>


                {{-- REQUEST 7 --}}
                <tr
                    class="pickup-request-row"
                    data-id="PU-1022"
                    data-seller="The Daily Market"
                    data-area="Victoria"
                    data-status="pending"
                >

                    <td>

                        <strong class="request-id">
                            PU-1022
                        </strong>

                        <span class="request-reference">
                            LMR-PU-241022
                        </span>

                    </td>


                    <td>

                        <div class="seller-information">

                            <span class="seller-avatar">
                                DM
                            </span>

                            <div>

                                <strong>
                                    The Daily Market
                                </strong>

                                <span>
                                    Seller
                                </span>

                            </div>

                        </div>

                    </td>


                    <td>

                        <strong class="address-main">
                            Victoria, Laguna
                        </strong>

                        <span class="address-secondary">
                            Brgy. San Roque
                        </span>

                    </td>


                    <td>
                        <strong>8</strong>
                    </td>


                    <td>

                        <strong>
                            Yesterday
                        </strong>

                        <span class="request-time">
                            3:41 PM
                        </span>

                    </td>


                    <td>

                        <span class="pickup-status pending">
                            Pending
                        </span>

                    </td>


                    <td>

                        <span class="unassigned-text">
                            Not assigned
                        </span>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="review-request-button"
                            data-request="PU-1022"
                        >
                            Review
                        </button>

                    </td>

                </tr>


            </tbody>

        </table>

    </div>


    <div
        class="pickup-empty-state"
        id="pickupEmptyState"
    >

        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.6"
        >
            <circle cx="11" cy="11" r="7"/>
            <path d="m20 20-4-4"/>
        </svg>

        <strong>
            No pickup requests found
        </strong>

        <span>
            Try changing your search or filter.
        </span>

    </div>

</section>


{{-- =========================================================
     REQUEST DETAILS DRAWER
     ========================================================= --}}

<div
    class="pickup-drawer-overlay"
    id="pickupDrawerOverlay"
></div>


<aside
    class="pickup-drawer"
    id="pickupDrawer"
    aria-hidden="true"
>

    <div class="drawer-header">

        <div>

            <span class="pickup-eyebrow">
                PICKUP REQUEST
            </span>

            <h2 id="drawerRequestId">
                PU-1028
            </h2>

        </div>


        <button
            type="button"
            class="drawer-close-button"
            id="drawerCloseButton"
            aria-label="Close request details"
        >

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path d="M18 6 6 18"/>
                <path d="m6 6 12 12"/>
            </svg>

        </button>

    </div>


    <div class="drawer-content">


        {{-- Status --}}
        <div class="drawer-status-row">

            <span>
                Current Status
            </span>

            <span
                class="pickup-status pending"
                id="drawerStatus"
            >
                Pending
            </span>

        </div>


        {{-- Seller --}}
        <section class="drawer-section">

            <div class="drawer-section-heading">

                <span class="drawer-section-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 21a8 8 0 0 1 16 0"/>
                    </svg>

                </span>

                <div>

                    <span>
                        SELLER
                    </span>

                    <strong>
                        Seller Information
                    </strong>

                </div>

            </div>


            <div class="drawer-information-grid">

                <div>

                    <span>
                        Business
                    </span>

                    <strong id="drawerSeller">
                        Maven Apparel
                    </strong>

                </div>

                <div>

                    <span>
                        Contact
                    </span>

                    <strong>
                        0917 824 1063
                    </strong>

                </div>

                <div class="full">

                    <span>
                        Email
                    </span>

                    <strong>
                        seller@lumear-demo.com
                    </strong>

                </div>

            </div>

        </section>


        {{-- Pickup address --}}
        <section class="drawer-section">

            <div class="drawer-section-heading">

                <span class="drawer-section-icon">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
                        <circle cx="12" cy="10" r="2"/>
                    </svg>

                </span>

                <div>

                    <span>
                        COLLECTION POINT
                    </span>

                    <strong>
                        Pickup Address
                    </strong>

                </div>

            </div>


            <div class="address-card">

                <strong id="drawerAddress">
                    Santa Cruz, Laguna
                </strong>

                <span id="drawerBarangay">
                    Brgy. Bubukal
                </span>

                <p>
                    128 Sample Street, Commercial Area
                </p>

            </div>

        </section>


        {{-- Parcel information --}}
        <section class="drawer-section">

            <div class="drawer-section-heading">

                <span class="drawer-section-icon">

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

                </span>

                <div>

                    <span>
                        PARCEL DETAILS
                    </span>

                    <strong>
                        Collection Information
                    </strong>

                </div>

            </div>


            <div class="parcel-information-grid">

                <div>

                    <span>
                        Total Parcels
                    </span>

                    <strong id="drawerParcelCount">
                        4
                    </strong>

                </div>

                <div>

                    <span>
                        Pickup Type
                    </span>

                    <strong>
                        Standard
                    </strong>

                </div>

                <div>

                    <span>
                        Preferred Date
                    </span>

                    <strong>
                        Today
                    </strong>

                </div>

                <div>

                    <span>
                        Time Window
                    </span>

                    <strong>
                        1:00 PM – 4:00 PM
                    </strong>

                </div>

            </div>

        </section>


        {{-- Timeline --}}
        <section class="drawer-section">

            <div class="drawer-section-heading">

                <span class="drawer-section-icon">

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

                </span>

                <div>

                    <span>
                        HISTORY
                    </span>

                    <strong>
                        Request Timeline
                    </strong>

                </div>

            </div>


            <div class="request-timeline">

                <div class="timeline-entry completed">

                    <span class="timeline-dot"></span>

                    <div>

                        <strong>
                            Pickup request submitted
                        </strong>

                        <p>
                            Seller requested parcel collection.
                        </p>

                        <small>
                            Today • 10:42 AM
                        </small>

                    </div>

                </div>


                <div
                    class="timeline-entry"
                    id="reviewTimelineEntry"
                >

                    <span class="timeline-dot"></span>

                    <div>

                        <strong id="reviewTimelineTitle">
                            Waiting for logistics review
                        </strong>

                        <p id="reviewTimelineDescription">
                            Approve or reject this pickup request.
                        </p>

                    </div>

                </div>

            </div>

        </section>


        {{-- Rider assignment --}}
        <section
            class="drawer-section rider-assignment-section"
            id="riderAssignmentSection"
        >

            <div class="drawer-section-heading">

                <span class="drawer-section-icon">

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

                    <span>
                        RIDER ASSIGNMENT
                    </span>

                    <strong>
                        Assign Pickup Rider
                    </strong>

                </div>

            </div>


            <label
                class="drawer-field-label"
                for="pickupRiderSelect"
            >
                Available rider
            </label>


            <select
                id="pickupRiderSelect"
                class="drawer-select"
            >

                <option value="">
                    Select an available rider
                </option>

                <option value="Juan Reyes">
                    Juan Reyes — Santa Cruz Area
                </option>

                <option value="Andrea Santos">
                    Andrea Santos — Victoria Area
                </option>

                <option value="Daniel Perez">
                    Daniel Perez — Pagsanjan Area
                </option>

            </select>


            <button
                type="button"
                class="assign-rider-button"
                id="assignRiderButton"
            >
                Assign Rider
            </button>

        </section>


    </div>


    {{-- Bottom actions --}}
    <div
        class="drawer-actions"
        id="drawerActions"
    >

        <button
            type="button"
            class="reject-request-button"
            id="rejectRequestButton"
        >
            Reject Request
        </button>


        <button
            type="button"
            class="approve-request-button"
            id="approveRequestButton"
        >

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path d="m7 12 3 3 7-7"/>
            </svg>

            Approve Pickup

        </button>

    </div>

</aside>


{{-- =========================================================
     REJECTION MODAL
     ========================================================= --}}

<div
    class="pickup-modal-overlay"
    id="rejectionModal"
>

    <div class="pickup-modal">

        <div class="pickup-modal-icon">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <circle cx="12" cy="12" r="9"/>
                <path d="m9 9 6 6"/>
                <path d="m15 9-6 6"/>
            </svg>

        </div>


        <h3>
            Reject Pickup Request?
        </h3>

        <p>
            Please provide a reason. This information
            will eventually be sent back to the seller.
        </p>


        <label for="rejectionReason">
            Reason for rejection
        </label>


        <textarea
            id="rejectionReason"
            rows="4"
            placeholder="Example: Pickup address is outside the supported collection area."
        ></textarea>


        <div class="pickup-modal-actions">

            <button
                type="button"
                class="modal-cancel-button"
                id="cancelRejectionButton"
            >
                Cancel
            </button>

            <button
                type="button"
                class="modal-reject-button"
                id="confirmRejectionButton"
            >
                Confirm Rejection
            </button>

        </div>

    </div>

</div>


{{-- =========================================================
     TOAST
     ========================================================= --}}

<div
    class="pickup-toast"
    id="pickupToast"
>

    <span class="toast-icon">
        ✓
    </span>

    <div>

        <strong id="toastTitle">
            Request updated
        </strong>

        <span id="toastMessage">
            Pickup request has been updated.
        </span>

    </div>

</div>


<script src="{{ asset('js/logistics/pickup.js') }}"></script>

@endsection