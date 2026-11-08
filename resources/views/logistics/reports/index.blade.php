@extends('layouts.logistics')

@section('title', 'Generate Reports | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/reports.css') }}">
@endpush

@section('content')

<div class="gr-page" id="logisticsReportsApp">

    {{-- ==========================================
         PAGE HEADER
         ========================================== --}}

    <header class="gr-header">

        <div>
            <p class="gr-eyebrow">
                LOGISTICS / ANALYTICS & REPORTING
            </p>

            <h1>Generate Reports</h1>

            <p>
                Review logistics activity, monitor operational
                performance, and export reports for your sorting center.
            </p>
        </div>

        <div class="gr-header-actions">
            <button
                type="button"
                class="gr-btn gr-btn-primary"
                id="grExport"
            >
                Download PDF Report
            </button>
        </div>

    </header>

    {{-- ==========================================
         REPORT FILTERS
         ========================================== --}}

    <section class="gr-filter-panel">

        <div class="gr-filter-heading">

            <div>
                <p class="gr-eyebrow">
                    REPORT CONFIGURATION
                </p>

                <h2>Report Filters</h2>
            </div>

            <button
                type="button"
                class="gr-reset"
                id="grReset"
            >
                Reset Filters
            </button>

        </div>

        <div class="gr-filter-grid">

            {{-- Quick Date Range --}}
            <div class="gr-field">

                <label for="grQuickRange">
                    Quick Date Range
                </label>

                <select id="grQuickRange">
                    <option value="custom">
                        Custom Date Range
                    </option>
                    <option value="today">Today</option>
                    <option value="3">Past 3 Days</option>
                    <option value="7">Past 7 Days</option>
                    <option value="30">Past 30 Days</option>
                </select>

            </div>

            {{-- Date From --}}
            <div class="gr-field">

                <label for="grFrom">
                    Date From
                </label>

                <input
                    type="date"
                    id="grFrom"
                >

            </div>

            {{-- Date To --}}
            <div class="gr-field">

                <label for="grTo">
                    Date To
                </label>

                <input
                    type="date"
                    id="grTo"
                >

            </div>

            {{-- Generate Button --}}
            <div class="gr-field gr-field-action">

                <button
                    type="button"
                    class="gr-btn gr-btn-primary"
                    id="grGenerate"
                >
                    Generate Report
                </button>

            </div>

            {{-- Report Type --}}
            <div class="gr-field">

                <label for="grType">
                    Report Type
                </label>

                <select id="grType">
                    <option value="all">
                        All Logistics Modules
                    </option>
                    <option value="pickup">
                        Pickup Requests
                    </option>
                    <option value="incoming">
                        Incoming Parcels
                    </option>
                    <option value="sorting">
                        Parcel Sorting
                    </option>
                    <option value="assignment">
                        Delivery Assignment
                    </option>
                    <option value="delivery">
                        Delivery Monitoring
                    </option>
                    <option value="rider">
                        Rider Performance
                    </option>
                </select>

            </div>

        </div>

        <p
            class="gr-filter-error"
            id="grFilterError"
            role="alert"
            hidden
        ></p>

    </section>

    {{-- ==========================================
         STATISTICS
         ========================================== --}}

    <section class="gr-stats">

        <article class="gr-stat">

            <span class="gr-stat-icon blue">▣</span>

            <div>
                <small>Total Records</small>
                <strong id="grTotal">0</strong>
                <span>Within selected period</span>
            </div>

        </article>

        <article class="gr-stat">

            <span class="gr-stat-icon green">✓</span>

            <div>
                <small>Completed</small>
                <strong id="grCompleted">0</strong>
                <span>Finished operations</span>
            </div>

        </article>

        <article class="gr-stat">

            <span class="gr-stat-icon amber">◷</span>

            <div>
                <small>In Progress</small>
                <strong id="grProgress">0</strong>
                <span>Active operations</span>
            </div>

        </article>

        <article class="gr-stat">

            <span class="gr-stat-icon red">!</span>

            <div>
                <small>Exceptions</small>
                <strong id="grExceptions">0</strong>
                <span>Delayed, failed, or rejected</span>
            </div>

        </article>

    </section>

    {{-- ==========================================
         ANALYTICS
         ========================================== --}}

    <section class="gr-analytics">

        {{-- Operations Chart --}}
        <article class="gr-panel">

            <div class="gr-panel-header">

                <div>
                    <p class="gr-eyebrow">
                        ACTIVITY ANALYSIS
                    </p>

                    <h2>Operations by Date</h2>

                    <p>
                        Number of records for each day
                        in the selected period.
                    </p>
                </div>

                <span
                    class="gr-counter"
                    id="grChartPeriod"
                >
                    Selected Period
                </span>

            </div>

            <div
                class="gr-chart"
                id="grChart"
                role="img"
                aria-label="Bar chart showing operations by date"
            ></div>

        </article>

        {{-- Status Breakdown --}}
        <article class="gr-panel">

            <div class="gr-panel-header">

                <div>
                    <p class="gr-eyebrow">
                        STATUS OVERVIEW
                    </p>

                    <h2>Report Breakdown</h2>

                    <p>
                        Distribution of records
                        by operational status.
                    </p>
                </div>

            </div>

            <div
                class="gr-breakdown"
                id="grBreakdown"
            ></div>

        </article>

    </section>

    {{-- ==========================================
         REPORT RECORDS
         ========================================== --}}

    <section class="gr-panel gr-record-panel">

        <div class="gr-panel-header">

            <div>
                <p class="gr-eyebrow">
                    OPERATIONAL RECORDS
                </p>

                <h2>Report Records</h2>

                <p>
                    Detailed logistics records
                    matching the selected filters.
                </p>
            </div>

            <span
                class="gr-counter"
                id="grRecordCount"
            >
                0 records
            </span>

        </div>

        {{-- Search and Status Filter --}}
        <div class="gr-toolbar">

            <input
                type="search"
                id="grSearch"
                placeholder="Search records, riders, locations..."
                aria-label="Search report records"
            >

            <select
                id="grStatus"
                aria-label="Filter records by status"
            >
                <option value="all">
                    All Statuses
                </option>

                <option value="Completed">
                    Completed
                </option>

                <option value="In Progress">
                    In Progress
                </option>

                <option value="Exception">
                    Exception
                </option>
            </select>

        </div>

        {{-- Report Table --}}
        <div class="gr-table-scroll">

            <table class="gr-table">

                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Date</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>Assigned To</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody id="grTableBody"></tbody>

            </table>

        </div>

        {{-- Empty State --}}
        <div
            class="gr-empty"
            id="grEmpty"
            hidden
        >
            No logistics records found
            for the selected filters.
        </div>

        {{-- Pagination --}}
        <div class="gr-footer">

            <span id="grShowing">
                Showing 0 records
            </span>

            <div class="gr-pages">

                <button
                    type="button"
                    id="grPrev"
                    disabled
                >
                    Previous
                </button>

                <span id="grPageLabel">
                    Page 1 of 1
                </span>

                <button
                    type="button"
                    id="grNext"
                    disabled
                >
                    Next
                </button>

            </div>

        </div>

    </section>

    {{-- ==========================================
         GENERATED REPORT HISTORY
         ========================================== --}}

    <section class="gr-panel gr-record-panel">

        <div class="gr-panel-header">

            <div>
                <p class="gr-eyebrow">
                    OFFICIAL DOCUMENTS
                </p>

                <h2>Generated Reports History</h2>

                <p>
                    Previously generated PDF reports.
                </p>
            </div>

        </div>

        <div class="gr-table-scroll">

            <table class="gr-table">

                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Type</th>
                        <th>Date Range</th>
                        <th>Records</th>
                        <th>Generated</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="grHistoryBody">
                    <tr>
                        <td colspan="6">
                            Loading report history...
                        </td>
                    </tr>
                </tbody>

            </table>

        </div>

    </section>

    {{-- ==========================================
         REPORT INFORMATION
         ========================================== --}}

    <p class="gr-demo-note">
        Report information is requested from the LUMEAR
        Logistics reporting API. Results depend on the
        records available in the connected database.
    </p>

    {{-- Toast Notification --}}
    <div
        class="gr-toast"
        id="grToast"
        role="status"
        aria-live="polite"
    ></div>

</div>

@endsection

@push('scripts')
<script
    src="{{ asset('js/logistics/reports.js') }}"
    defer
></script>
@endpush
