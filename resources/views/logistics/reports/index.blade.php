
@extends('layouts.logistics')

@section('title', 'Generate Reports | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/reports.css') }}">
@endpush

@section('content')
<div class="gr-page" id="logisticsReportsApp">
    <header class="gr-header">
        <div>
            <p class="gr-eyebrow">LOGISTICS / ANALYTICS & REPORTING</p>
            <h1>Generate Reports</h1>
            <p>Review logistics activity, monitor operational performance, and export reports for your sorting center.</p>
        </div>
        <div class="gr-header-actions">
            <button type="button"
                    class="gr-btn gr-btn-primary"
                    id="grExport">
                Download PDF Report
            </button>
        </div>
    </header>

    <section class="gr-filter-panel">
        <div class="gr-filter-heading">
            <div>
                <p class="gr-eyebrow">REPORT CONFIGURATION</p>
                <h2>Report Filters</h2>
            </div>
            <button type="button" class="gr-reset" id="grReset">Reset Filters</button>
        </div>

        <div class="gr-filter-grid">
            <<div class="gr-field">
                <label for="grQuickRange">Quick Date Range</label>
                <select id="grQuickRange">
                    <option value="custom">Custom Date Range</option>
                    <option value="today">Today</option>
                    <option value="3">Past 3 Days</option>
                    <option value="7">Past 7 Days</option>
                    <option value="30">Past 30 Days</option>
                </select>
            </div>

            <div class="gr-field">
                <label for="grFrom">Date From</label>
                <input type="date" id="grFrom" value="2026-10-01">
            </div>

            <div class="gr-field">
                <label for="grTo">Date To</label>
                <input type="date" id="grTo" value="2026-10-31">
            </div>

            <div class="gr-field gr-field-action">
                <button type="button" class="gr-btn gr-btn-primary" id="grGenerate">
                    Generate Report
                </button>
            </div>
        </div>
        <p class="gr-filter-error" id="grFilterError" role="alert" hidden></p>
    </section>

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

    <section class="gr-analytics">
        <article class="gr-panel">
            <div class="gr-panel-header">
                <div>
                    <p class="gr-eyebrow">ACTIVITY ANALYSIS</p>
                    <h2>Operations by Date</h2>
                    <p>Number of records for each day in the selected period.</p>
                </div>
                <span class="gr-counter" id="grChartPeriod">Selected Period</span>
            </div>
            <div class="gr-chart" id="grChart" role="img" aria-label="Bar chart showing operations by date"></div>
        </article>

        <article class="gr-panel">
            <div class="gr-panel-header">
                <div>
                    <p class="gr-eyebrow">STATUS OVERVIEW</p>
                    <h2>Report Breakdown</h2>
                    <p>Distribution of records by operational status.</p>
                </div>
            </div>
            <div class="gr-breakdown" id="grBreakdown"></div>
        </article>
    </section>

    <section class="gr-panel gr-record-panel">
        <div class="gr-panel-header">
            <div>
                <p class="gr-eyebrow">OFFICIAL DOCUMENTS</p>
                <h2>Generated Reports History</h2>
                <p>Previously generated PDF reports.</p>
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
                <tbody id="grHistoryBody"></tbody>
            </table>
        </div>
    </section>

    <p class="gr-demo-note">
        Demo report: all records are sample data. Exports and printouts do not contain live system information.
        Backend integration will be required to generate reports from the shared MySQL database.
    </p>

    <div class="gr-toast" id="grToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/reports.js') }}" defer></script>
@endpush
