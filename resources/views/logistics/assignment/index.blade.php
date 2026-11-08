
@extends('layouts.logistics')

@section('title', 'Delivery Assignment | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/assignment.css') }}">
@endpush

@section('content')
<div class="da-page" id="deliveryAssignmentApp">

    <header class="da-heading">
        <div>
            <p class="da-eyebrow">DELIVERY OPERATIONS</p>
            <h1>Delivery Assignment</h1>
            <p class="da-description">
                Assign sorted parcels to available riders, manage delivery
                workloads, and prepare shipments for dispatch.
            </p>
        </div>

        <div class="da-heading-actions">
            <button type="button" class="da-btn da-btn-outline" id="daRefresh">
                <span>↻</span> Refresh
            </button>
            <button type="button" class="da-btn da-btn-primary" id="daAssignSelected">
                <span>＋</span> Assign Selected
            </button>
        </div>
    </header>

    {{-- STATISTICS --}}
    <section class="da-stats" aria-label="Delivery assignment statistics">
        <article class="da-stat">
            <div class="da-stat-icon da-icon-amber">◷</div>
            <div>
                <span>Awaiting Assignment</span>
                <strong id="daPendingCount">0</strong>
                <small>Sorted parcels without riders</small>
            </div>
        </article>

        <article class="da-stat">
            <div class="da-stat-icon da-icon-blue">▣</div>
            <div>
                <span>Assigned Parcels</span>
                <strong id="daAssignedCount">0</strong>
                <small>Ready for dispatch</small>
            </div>
        </article>

        <article class="da-stat">
            <div class="da-stat-icon da-icon-green">✓</div>
            <div>
                <span>Dispatched Parcels</span>
                <strong id="daDispatchedCount">0</strong>
                <small>Released to riders</small>
            </div>
        </article>

        <article class="da-stat">
            <div class="da-stat-icon da-icon-purple">♧</div>
            <div>
                <span>Available Riders</span>
                <strong id="daAvailableRiders">0</strong>
                <small>Active riders with capacity</small>
            </div>
        </article>
    </section>

    {{-- RIDER AVAILABILITY --}}
    <section class="da-panel da-riders-panel">
        <div class="da-panel-heading">
            <div>
                <p class="da-eyebrow">RIDER OPERATIONS</p>
                <h2>Rider Availability</h2>
                <p>Only riders whose registered municipality and province match this hub can receive assignments.</p>
            </div>
            <span class="da-section-pill" id="daRiderTotal">0 riders</span>
        </div>

        <div class="da-rider-grid" id="daRiderGrid"></div>
    </section>

    {{-- PARCEL FILTERS --}}
    <section class="da-toolbar">
        <div class="da-search">
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-4-4"></path>
            </svg>

            <input
                type="search"
                id="daSearch"
                placeholder="Search tracking ID, recipient, rider or destination..."
                aria-label="Search delivery assignments"
            >
        </div>

        <div class="da-filter-group">
            <select id="daStatusFilter" aria-label="Filter by assignment status">
                <option value="all">All Status</option>
                <option value="Awaiting Assignment">Awaiting Assignment</option>
                <option value="Assigned">Assigned</option>
                <option value="Dispatched">Dispatched</option>
            </select>

            <select id="daAreaFilter" aria-label="Filter by destination">
                <option value="all">All Areas</option>
            </select>
        </div>
    </section>

    {{-- ASSIGNMENT TABLE --}}
    <section class="da-panel">
        <div class="da-panel-heading">
            <div>
                <p class="da-eyebrow">PARCEL DISPATCH MANAGEMENT</p>
                <h2>Delivery Assignment Queue</h2>
                <p>Assign riders to sorted parcels and monitor dispatch preparation.</p>
            </div>

            <div class="da-panel-actions">
                <span class="da-section-pill" id="daVisibleCount">0 parcels</span>
                <button type="button" class="da-btn da-btn-outline" id="daExport">
                    Export Assignments
                </button>
            </div>
        </div>

        <div class="da-table-scroll">
            <table class="da-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="daSelectAll"
                                   aria-label="Select all eligible parcels">
                        </th>
                        <th>Tracking ID</th>
                        <th>Recipient</th>
                        <th>Destination</th>
                        <th>Parcel</th>
                        <th>Assigned Rider</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="daTableBody"></tbody>
            </table>
        </div>

        <div class="da-empty" id="daEmpty" hidden>
            <div class="da-empty-icon">▢</div>
            <strong>No matching parcels</strong>
            <p>Try changing the search or filters.</p>
        </div>

        <footer class="da-table-footer">
            <span id="daShowing">Showing 0 parcels</span>

            <div class="da-pagination">
                <button type="button" id="daPrev">Previous</button>
                <span id="daPageLabel">Page 1 of 1</span>
                <button type="button" id="daNext">Next</button>
            </div>
        </footer>
    </section>

    {{-- SELECTED PARCELS BAR --}}
    <div class="da-bulk-bar" id="daBulkBar" hidden>
        <strong id="daSelectedCount">0 parcels selected</strong>

        <div>
            <button type="button" class="da-btn da-btn-outline" id="daClearSelected">
                Clear Selection
            </button>
            <button type="button" class="da-btn da-btn-primary" id="daBulkAssign">
                Assign Rider
            </button>
        </div>
    </div>

    {{-- RECENT ASSIGNMENTS --}}
    <section class="da-panel da-history-panel">
        <div class="da-panel-heading">
            <div>
                <p class="da-eyebrow">DELIVERY WORKFLOW</p>
                <h2>Assignment Overview</h2>
                <p>Review rider workloads and dispatch readiness.</p>
            </div>
        </div>

        <div class="da-overview-grid" id="daOverviewGrid"></div>
    </section>

    <div class="da-demo-note">
        <span></span>
        Frontend demonstration only. Delivery assignments are not yet saved
        to MySQL or synchronized with the Rider mobile application.
    </div>

    {{-- ASSIGN / REASSIGN MODAL --}}
    <div class="da-modal" id="daAssignModal" aria-hidden="true">
        <div class="da-dialog" role="dialog" aria-modal="true"
             aria-labelledby="daAssignTitle">
            <div class="da-dialog-header">
                <div>
                    <p class="da-eyebrow">RIDER ASSIGNMENT</p>
                    <h2 id="daAssignTitle">Assign Delivery Rider</h2>
                    <p>Choose an available rider for the selected parcels.</p>
                </div>

                <button type="button" class="da-close" data-da-close
                        aria-label="Close">×</button>
            </div>

            <form id="daAssignForm">
                <div class="da-selection-summary">
                    <span>SELECTED PARCELS</span>
                    <strong id="daAssignSummary">0 parcels</strong>
                    <p id="daAssignDestinations"></p>
                </div>

                <div class="da-field">
                    <label for="daRiderSelect">Available Rider *</label>
                    <select id="daRiderSelect" required>
                        <option value="">Select a rider</option>
                    </select>
                </div>

                <div class="da-field">
                    <label for="daAssignmentNotes">Assignment Instructions</label>
                    <textarea id="daAssignmentNotes" rows="3" maxlength="500"
                              placeholder="Optional delivery instructions"></textarea>
                </div>

                <div class="da-rider-preview" id="daRiderPreview">
                    Select a rider to view their current delivery capacity.
                </div>

                <div class="da-dialog-footer">
                    <button type="button" class="da-btn da-btn-outline"
                            data-da-close>Cancel</button>
                    <button type="submit" class="da-btn da-btn-primary">
                        Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- PARCEL DETAILS MODAL --}}
    <div class="da-modal" id="daDetailsModal" aria-hidden="true">
        <div class="da-dialog" role="dialog" aria-modal="true"
             aria-labelledby="daDetailsTitle">
            <div class="da-dialog-header">
                <div>
                    <p class="da-eyebrow">DELIVERY INFORMATION</p>
                    <h2 id="daDetailsTitle">Parcel Assignment Details</h2>
                    <p id="daDetailsSubtitle">Tracking information</p>
                </div>

                <button type="button" class="da-close" data-da-close
                        aria-label="Close">×</button>
            </div>

            <div class="da-detail-grid" id="daDetailsGrid"></div>
            <div class="da-detail-note" id="daDetailsNote"></div>

            <div class="da-dialog-footer">
                <button type="button" class="da-btn da-btn-outline"
                        data-da-close>Close</button>
                <button type="button" class="da-btn da-btn-primary"
                        id="daDetailsAction">Assign Rider</button>
            </div>
        </div>
    </div>

    {{-- DISPATCH CONFIRMATION --}}
    <div class="da-modal" id="daDispatchModal" aria-hidden="true">
        <div class="da-dialog da-dialog-small" role="dialog" aria-modal="true"
             aria-labelledby="daDispatchTitle">
            <div class="da-dialog-header">
                <div>
                    <p class="da-eyebrow">DISPATCH CONFIRMATION</p>
                    <h2 id="daDispatchTitle">Confirm Parcel Dispatch</h2>
                    <p>Confirm that the parcel has been handed over to the rider.</p>
                </div>

                <button type="button" class="da-close" data-da-close
                        aria-label="Close">×</button>
            </div>

            <div class="da-selection-summary">
                <span>TRACKING ID</span>
                <strong id="daDispatchTracking">—</strong>
                <p id="daDispatchRider">—</p>
            </div>

            <div class="da-detail-note">
                Confirm only after the assigned rider has received the parcel.
                The status will change to <strong>Dispatched</strong>.
            </div>

            <div class="da-dialog-footer">
                <button type="button" class="da-btn da-btn-outline"
                        data-da-close>Cancel</button>
                <button type="button" class="da-btn da-btn-primary"
                        id="daConfirmDispatch">Confirm Dispatch</button>
            </div>
        </div>
    </div>

    <div class="da-toast" id="daToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/assignment.js') }}" defer></script>
@endpush
