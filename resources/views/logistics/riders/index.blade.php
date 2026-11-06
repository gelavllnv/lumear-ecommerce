@extends('layouts.logistics')

@section('title', 'Rider List | LUMEAR Logistics')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/logistics/riders.css') }}"
>
@endpush

@section('content')
<div
    class="rm-page"
    id="riderManagementApp"
    data-page="riders"
>
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">
                LOGISTICS / RIDER MANAGEMENT
            </p>

            <h1>Rider List</h1>

            <p>
                View approved delivery riders, seller pickup riders,
                and hub-to-hub drivers. Manage their availability
                and review account status.
            </p>
        </div>

        <div class="rm-header-actions">
            <a
                class="rm-button rm-button-outline"
                href="{{ route('logistics.riders.applications') }}"
            >
                Rider Applications
            </a>

            <button
                type="button"
                class="rm-button rm-button-primary"
                id="rmExport"
            >
                Export Rider List
            </button>
        </div>
    </header>

    <section class="rm-stats">
        <article class="rm-stat">
            <span class="rm-stat-icon blue">♙</span>

            <div>
                <small>Total Riders</small>
                <strong id="rmTotal">0</strong>
                <span>All approved job types</span>
            </div>
        </article>

        <article class="rm-stat">
            <span class="rm-stat-icon green">✓</span>

            <div>
                <small>Available</small>
                <strong id="rmAvailable">0</strong>
                <span>Marked available for work</span>
            </div>
        </article>

        <article class="rm-stat">
            <span class="rm-stat-icon amber">◷</span>

            <div>
                <small>Offline</small>
                <strong id="rmOffline">0</strong>
                <span>Not currently available</span>
            </div>
        </article>

        <article class="rm-stat">
            <span class="rm-stat-icon red">!</span>

            <div>
                <small>Suspended</small>
                <strong id="rmSuspended">0</strong>
                <span>Temporarily restricted</span>
            </div>
        </article>
    </section>

    <section class="rm-panel">
        <div class="rm-panel-header">
            <div>
                <p class="rm-eyebrow">
                    RIDER DIRECTORY
                </p>

                <h2>Registered Riders and Drivers</h2>

                <p>
                    Approved Delivery, Seller Pickup, and
                    Long-distance Handover accounts assigned
                    to your logistics provider appear here.
                </p>
            </div>

            <span class="rm-counter" id="rmCount">
                0 riders
            </span>
        </div>

        <div class="rm-toolbar">
            <input
                type="search"
                id="rmSearch"
                placeholder="Search rider ID, name, email, municipality or job type..."
                aria-label="Search riders and drivers"
            >

            <select
                id="rmFilter"
                aria-label="Filter account status"
            >
                <option value="All">All Statuses</option>
                <option value="Available">Available</option>
                <option value="Offline">Offline</option>
                <option value="Suspended">Suspended</option>
            </select>
        </div>

        <div class="rm-table-scroll">
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Rider ID</th>
                        <th>Rider / Driver</th>
                        <th>Assigned Area</th>
                        <th>Vehicle / Selected Job Type</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="rmTableBody"></tbody>
            </table>
        </div>

        <div class="rm-empty" id="rmEmpty" hidden>
            No approved riders or drivers match your search.
        </div>

        <div class="rm-footer">
            <span id="rmShowing">
                Showing 0 riders
            </span>

            <div class="rm-pages">
                <button type="button" id="rmPrev">
                    Previous
                </button>

                <span id="rmPageLabel">
                    Page 1 of 1
                </span>

                <button type="button" id="rmNext">
                    Next
                </button>
            </div>
        </div>
    </section>

    <p class="rm-note">
        Job types are selected during registration in the rider app.
        Logistics can manage availability and assign matching tasks,
        but cannot change the registered job type here.
        Availability on this page is managed manually.
    </p>

    <div
        class="rm-modal"
        id="rmDetailModal"
        aria-hidden="true"
    >
        <div
            class="rm-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rmDetailTitle"
        >
            <div class="rm-dialog-header">
                <div>
                    <p class="rm-eyebrow">
                        RIDER / DRIVER PROFILE
                    </p>

                    <h2 id="rmDetailTitle">
                        Account Information
                    </h2>

                    <p id="rmDetailSubtitle"></p>
                </div>

                <button
                    type="button"
                    class="rm-close"
                    data-rm-close
                    aria-label="Close"
                >
                    ×
                </button>
            </div>

            <div
                class="rm-detail-grid"
                id="rmDetailGrid"
            ></div>

            <div class="rm-dialog-footer">
                <button
                    type="button"
                    class="rm-button rm-button-outline"
                    data-rm-close
                >
                    Close
                </button>

                <button
                    type="button"
                    class="rm-button rm-button-primary"
                    id="rmManageFromDetail"
                >
                    Manage Status
                </button>
            </div>
        </div>
    </div>

    <div
        class="rm-modal"
        id="rmStatusModal"
        aria-hidden="true"
    >
        <div
            class="rm-dialog rm-dialog-small"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rmStatusTitle"
        >
            <div class="rm-dialog-header">
                <div>
                    <p class="rm-eyebrow">
                        RIDER / DRIVER ACCOUNT
                    </p>

                    <h2 id="rmStatusTitle">
                        Manage Account Status
                    </h2>

                    <p id="rmStatusSubtitle"></p>
                </div>

                <button
                    type="button"
                    class="rm-close"
                    data-rm-close
                    aria-label="Close"
                >
                    ×
                </button>
            </div>

            <form id="rmStatusForm">
                <label class="rm-label" for="rmNewStatus">
                    Account Status
                </label>

                <select id="rmNewStatus" required>
                    <option value="Available">Available</option>
                    <option value="Offline">Offline</option>
                    <option value="Suspended">Suspended</option>
                </select>

                <label
                    class="rm-label rm-label-spaced"
                    for="rmStatusReason"
                >
                    Remarks
                </label>

                <textarea
                    id="rmStatusReason"
                    rows="4"
                    maxlength="500"
                    placeholder="Optional remarks; required for suspension..."
                ></textarea>

                <div class="rm-dialog-footer">
                    <button
                        type="button"
                        class="rm-button rm-button-outline"
                        data-rm-close
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="rm-button rm-button-primary"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        class="rm-toast"
        id="rmToast"
        role="status"
        aria-live="polite"
    ></div>
</div>
@endsection

@push('scripts')
<script
    src="{{ asset('js/logistics/riders.js') }}"
    defer
></script>
@endpush