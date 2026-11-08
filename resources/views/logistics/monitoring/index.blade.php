
@extends('layouts.logistics')

@section('title', 'Delivery Monitoring | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/monitoring.css') }}">
@endpush

@section('content')
<div class="dm-page" id="deliveryMonitoringApp">

    {{-- PAGE HEADER --}}
    <header class="dm-heading">
        <div>
            <p class="dm-eyebrow">LOGISTICS / DELIVERY OPERATIONS</p>
            <h1>Delivery Monitoring</h1>
            <p class="dm-description">
                Track dispatched parcels, monitor rider progress,
                review delivery exceptions, and manage delivery records.
            </p>
        </div>

        <div class="dm-heading-actions">
            <button type="button" class="dm-btn dm-btn-outline" id="dmRefresh">
                <span>↻</span> Refresh
            </button>
            <button type="button" class="dm-btn dm-btn-primary" id="dmExport">
                Export Report
            </button>
        </div>
    </header>

    {{-- STATISTICS --}}
    <section class="dm-stats" aria-label="Delivery statistics">
        <article class="dm-stat">
            <div class="dm-stat-icon dm-icon-blue">▣</div>
            <div>
                <span>Active Deliveries</span>
                <strong id="dmActiveCount">0</strong>
                <small>Dispatched or in transit</small>
            </div>
        </article>

        <article class="dm-stat">
            <div class="dm-stat-icon dm-icon-green">✓</div>
            <div>
                <span>Delivered</span>
                <strong id="dmDeliveredCount">0</strong>
                <small>Completed deliveries</small>
            </div>
        </article>

        <article class="dm-stat">
            <div class="dm-stat-icon dm-icon-amber">◷</div>
            <div>
                <span>Delayed Deliveries</span>
                <strong id="dmDelayedCount">0</strong>
                <small>Need attention</small>
            </div>
        </article>

        <article class="dm-stat">
            <div class="dm-stat-icon dm-icon-red">!</div>
            <div>
                <span>Delivery Exceptions</span>
                <strong id="dmFailedCount">0</strong>
                <small>Failed delivery attempts</small>
            </div>
        </article>
    </section>

    {{-- DELIVERY PROGRESS --}}
    <section class="dm-overview">
        <article class="dm-panel dm-progress-panel">
            <div class="dm-panel-heading">
                <div>
                    <p class="dm-eyebrow">OPERATIONAL OVERVIEW</p>
                    <h2>Delivery Progress</h2>
                    <p>Current distribution of delivery statuses.</p>
                </div>
            </div>

            <div class="dm-progress-content" id="dmProgressContent"></div>
        </article>

        <article class="dm-panel dm-alert-panel">
            <div class="dm-panel-heading">
                <div>
                    <p class="dm-eyebrow">ACTION REQUIRED</p>
                    <h2>Delivery Alerts</h2>
                    <p>Parcels that may require follow-up.</p>
                </div>

                <span class="dm-counter" id="dmAlertCount">0 alerts</span>
            </div>

            <div class="dm-alert-list" id="dmAlertList"></div>
        </article>
    </section>

    {{-- SEARCH AND FILTERS --}}
    <section class="dm-toolbar">
        <div class="dm-search">
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8"
                 aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-4-4"></path>
            </svg>

            <input
                type="search"
                id="dmSearch"
                placeholder="Search tracking ID, recipient, rider or destination..."
                aria-label="Search delivery records"
            >
        </div>

        <div class="dm-filters">
            <select id="dmStatusFilter" aria-label="Filter by delivery status">
                <option value="all">All Statuses</option>
                <option value="Dispatched">Dispatched</option>
                <option value="In Transit">In Transit</option>
                <option value="Out for Delivery">Out for Delivery</option>
                <option value="Delivered">Delivered</option>
                <option value="Delay Reported">Delay Reported</option>
                <option value="Delayed">Delayed</option>
                <option value="Failed Attempt">Failed Attempt</option>
            </select>

            <select id="dmRiderFilter" aria-label="Filter by rider">
                <option value="all">All Riders</option>
            </select>
        </div>
    </section>

    {{-- DELIVERY TABLE --}}
    <section class="dm-panel">
        <div class="dm-panel-heading">
            <div>
                <p class="dm-eyebrow">LIVE OPERATIONS DEMONSTRATION</p>
                <h2>Delivery Tracking Queue</h2>
                <p>Review delivery assignments and update their progress.</p>
            </div>

            <span class="dm-counter" id="dmResultCount">0 records</span>
        </div>

        <div class="dm-table-scroll">
            <table class="dm-table">
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>Recipient</th>
                        <th>Destination</th>
                        <th>Assigned Rider</th>
                        <th>Dispatch Date</th>
                        <th>Delivery Status</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="dmTableBody"></tbody>
            </table>
        </div>

        <div class="dm-empty" id="dmEmpty" hidden>
            <div class="dm-empty-icon">▢</div>
            <strong>No delivery records found</strong>
            <p>Try changing the search or filters.</p>
        </div>

        <footer class="dm-table-footer">
            <span id="dmShowing">Showing 0 deliveries</span>

            <div class="dm-pagination">
                <button type="button" id="dmPrev">Previous</button>
                <span id="dmPageLabel">Page 1 of 1</span>
                <button type="button" id="dmNext">Next</button>
            </div>
        </footer>
    </section>

    {{-- RIDER DELIVERY OVERVIEW --}}
    <section class="dm-panel">
        <div class="dm-panel-heading">
            <div>
                <p class="dm-eyebrow">RIDER PERFORMANCE</p>
                <h2>Rider Delivery Overview</h2>
                <p>Current parcel distribution and completed deliveries per rider.</p>
            </div>
        </div>

        <div class="dm-rider-grid" id="dmRiderGrid"></div>
    </section>

    <div class="dm-demo-note">
        <span></span>
        Reschedule notices go to the buyer and assigned rider. Delivery
        updates are recorded on the parcel timeline.
    </div>

    {{-- DETAILS MODAL --}}
    <div class="dm-modal" id="dmDetailsModal" aria-hidden="true">
        <div class="dm-dialog" role="dialog" aria-modal="true"
             aria-labelledby="dmDetailsTitle">

            <div class="dm-dialog-header">
                <div>
                    <p class="dm-eyebrow">PARCEL TRACKING</p>
                    <h2 id="dmDetailsTitle">Delivery Details</h2>
                    <p id="dmDetailsSubtitle">Parcel information</p>
                </div>

                <button type="button" class="dm-close"
                        data-dm-close aria-label="Close">×</button>
            </div>

            <div class="dm-detail-grid" id="dmDetailsGrid"></div>

            <div class="dm-timeline-section">
                <h3>Delivery Timeline</h3>
                <div class="dm-timeline" id="dmTimeline"></div>
            </div>

            <div class="dm-dialog-footer">
                <button type="button" class="dm-btn dm-btn-outline"
                        data-dm-close>Close</button>
                <button type="button" class="dm-btn dm-btn-primary"
                        id="dmDetailsUpdate">Update Status</button>
            </div>
        </div>
    </div>

    {{-- STATUS UPDATE MODAL --}}
    <div class="dm-modal" id="dmUpdateModal" aria-hidden="true">
        <div class="dm-dialog dm-dialog-small" role="dialog" aria-modal="true"
             aria-labelledby="dmUpdateTitle">

            <div class="dm-dialog-header">
                <div>
                    <p class="dm-eyebrow">DELIVERY PROGRESS</p>
                    <h2 id="dmUpdateTitle">Update Delivery Status</h2>
                    <p id="dmUpdateSubtitle">Select the latest delivery status.</p>
                </div>

                <button type="button" class="dm-close"
                        data-dm-close aria-label="Close">×</button>
            </div>

            <form id="dmUpdateForm">
                <div class="dm-field">
                    <label for="dmNewStatus">New Delivery Status *</label>
                    <select id="dmNewStatus" required>
                        <option value="">Select status</option>
                    </select>
                </div>

                <div class="dm-field" id="dmRescheduleField" hidden>
                    <label for="dmRescheduleFor">New delivery date and time *</label>
                    <input id="dmRescheduleFor" type="datetime-local">
                    <small>The same rider stays assigned to this parcel.</small>
                </div>

                <div class="dm-field">
                    <label for="dmUpdateNotes">Status Remarks</label>
                    <textarea id="dmUpdateNotes" rows="3" maxlength="500"
                              placeholder="Explain the delay or delivery issue"></textarea>
                </div>

                <div class="dm-dialog-footer">
                    <button type="button" class="dm-btn dm-btn-outline"
                            data-dm-close>Cancel</button>
                    <button type="submit" class="dm-btn dm-btn-primary">
                        Save Status Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="dm-toast" id="dmToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/monitoring.js') }}" defer></script>
@endpush
