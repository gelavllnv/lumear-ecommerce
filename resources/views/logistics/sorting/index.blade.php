@extends('layouts.logistics')

@section('title', 'Parcel Sorting | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/sorting.css') }}">
@endpush

@section('content')
<div class="sort-page" id="sortingApp">

    <header class="sort-heading">
        <div>
            <p class="sort-eyebrow">PARCEL OPERATIONS</p>
            <h1>Parcel Sorting</h1>
            <p class="sort-description">
                Organize verified incoming parcels by destination,
                manage sorting batches, and prepare shipments for dispatch.
            </p>
        </div>

        <div class="sort-heading-actions">
            <button type="button" class="sort-btn sort-btn-outline" id="sortRefresh">
                <span>↻</span> Refresh
            </button>
            <button type="button" class="sort-btn sort-btn-primary" id="sortNewBatch">
                <span>＋</span> Create Sorting Batch
            </button>
        </div>
    </header>

    {{-- STATISTICS --}}
    <section class="sort-stats" aria-label="Parcel sorting statistics">
        <article class="sort-stat">
            <div class="sort-stat-icon sort-icon-amber">◷</div>
            <div>
                <span>Unsorted Parcels</span>
                <strong id="sortUnsortedCount">0</strong>
                <small>Waiting to be assigned</small>
            </div>
        </article>

        <article class="sort-stat">
            <div class="sort-stat-icon sort-icon-blue">▣</div>
            <div>
                <span>Sorting in Progress</span>
                <strong id="sortProgressCount">0</strong>
                <small>Assigned to sorting batches</small>
            </div>
        </article>

        <article class="sort-stat">
            <div class="sort-stat-icon sort-icon-green">✓</div>
            <div>
                <span>Sorted Parcels</span>
                <strong id="sortSortedCount">0</strong>
                <small>Ready for delivery assignment</small>
            </div>
        </article>

        <article class="sort-stat">
            <div class="sort-stat-icon sort-icon-purple">▤</div>
            <div>
                <span>Active Batches</span>
                <strong id="sortBatchCount">0</strong>
                <small>Sorting groups in progress</small>
            </div>
        </article>
    </section>

    {{-- DESTINATION OVERVIEW --}}
    <section class="sort-destinations-panel">
        <div class="sort-section-heading">
            <div>
                <p class="sort-eyebrow">DESTINATION OVERVIEW</p>
                <h2>Sorting by Area</h2>
                <p>Parcel distribution across delivery destinations.</p>
            </div>
            <span class="sort-section-pill">Laguna Distribution</span>
        </div>

        <div class="sort-destination-grid" id="sortDestinationGrid"></div>
    </section>

    {{-- SEARCH AND FILTERS --}}
    <section class="sort-toolbar">
        <div class="sort-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-4-4"></path>
            </svg>

            <input
                id="sortSearch"
                type="search"
                placeholder="Search tracking ID, batch, recipient or destination..."
                aria-label="Search sorting queue"
            >
        </div>

        <div class="sort-filter-group">
            <select id="sortStatusFilter" aria-label="Filter by sorting status">
                <option value="all">All Status</option>
                <option value="Unsorted">Unsorted</option>
                <option value="In Progress">In Progress</option>
                <option value="Sorted">Sorted</option>
            </select>

            <select id="sortAreaFilter" aria-label="Filter by destination">
                <option value="all">All Areas</option>
            </select>
        </div>
    </section>

    {{-- SORTING QUEUE --}}
    <section class="sort-panel">
        <div class="sort-panel-header">
            <div>
                <p class="sort-eyebrow">SORTING OPERATIONS</p>
                <h2>Parcel Sorting Queue</h2>
                <p>Verified parcels waiting for sorting or already processed.</p>
            </div>

            <div class="sort-panel-actions">
                <span class="sort-count" id="sortVisibleCount">0 parcels</span>
                <button type="button" class="sort-btn sort-btn-outline" id="sortExport">
                    Export Manifest
                </button>
            </div>
        </div>

        <div class="sort-table-scroll">
            <table class="sort-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="sortSelectAll"
                                   aria-label="Select all visible parcels">
                        </th>
                        <th>Tracking ID</th>
                        <th>Recipient</th>
                        <th>Destination</th>
                        <th>Parcel Type</th>
                        <th>Sorting Batch</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="sortTableBody"></tbody>
            </table>
        </div>

        <div class="sort-empty" id="sortEmpty" hidden>
            <div class="sort-empty-icon">▢</div>
            <strong>No matching parcels</strong>
            <p>Try another tracking number or adjust your filters.</p>
        </div>

        <footer class="sort-table-footer">
            <span id="sortShowing">Showing 0 parcels</span>

            <div class="sort-pagination">
                <button type="button" id="sortPrev">Previous</button>
                <span id="sortPageLabel">Page 1 of 1</span>
                <button type="button" id="sortNext">Next</button>
            </div>
        </footer>
    </section>

    {{-- BULK ACTION BAR --}}
    <div class="sort-bulk-bar" id="sortBulkBar" hidden>
        <span id="sortSelectedCount">0 selected</span>

        <div>
            <button type="button" class="sort-btn sort-btn-outline" id="sortClearSelection">
                Clear
            </button>
            <button type="button" class="sort-btn sort-btn-primary" id="sortBulkAssign">
                Assign to Batch
            </button>
        </div>
    </div>

    {{-- BATCHES --}}
    <section class="sort-batches-panel">
        <div class="sort-section-heading">
            <div>
                <p class="sort-eyebrow">SORTING MANAGEMENT</p>
                <h2>Sorting Batches</h2>
                <p>Monitor parcel groups and their sorting progress.</p>
            </div>

            <span class="sort-section-pill" id="sortTotalBatches">0 batches</span>
        </div>

        <div class="sort-batches-grid" id="sortBatchesGrid"></div>
    </section>

    <div class="sort-bottom-note">
        <span class="sort-demo-dot"></span>
        UI demonstration — sample sorting data is not yet connected to
        Incoming Parcels, Seller orders, or Rider assignments.
    </div>

    {{-- CREATE BATCH MODAL --}}
    <div class="sort-modal" id="sortCreateBatchModal" aria-hidden="true">
        <div class="sort-dialog" role="dialog" aria-modal="true"
             aria-labelledby="sortCreateBatchTitle">
            <div class="sort-dialog-header">
                <div>
                    <p class="sort-eyebrow">BATCH MANAGEMENT</p>
                    <h2 id="sortCreateBatchTitle">Create Sorting Batch</h2>
                    <p>Prepare a destination-based sorting group.</p>
                </div>
                <button type="button" class="sort-close" data-sort-close
                        aria-label="Close">×</button>
            </div>

            <form id="sortCreateBatchForm">
                <div class="sort-form-grid">
                    <div class="sort-field">
                        <label for="sortBatchName">Batch Name *</label>
                        <input id="sortBatchName" name="batchName" required
                               maxlength="80" placeholder="Morning Sorting - Santa Cruz">
                    </div>

                    <div class="sort-field">
                        <label for="sortBatchArea">Destination *</label>
                        <select id="sortBatchArea" name="batchArea" required>
                            <option value="">Select destination</option>
                        </select>
                    </div>

                    <div class="sort-field sort-field-full">
                        <label for="sortBatchNotes">Sorting Instructions</label>
                        <textarea id="sortBatchNotes" name="batchNotes"
                                  rows="3" maxlength="500"
                                  placeholder="Optional sorting instructions"></textarea>
                    </div>
                </div>

                <div class="sort-dialog-footer">
                    <button type="button" class="sort-btn sort-btn-outline"
                            data-sort-close>Cancel</button>
                    <button type="submit" class="sort-btn sort-btn-primary">
                        Create Batch
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ASSIGN BATCH MODAL --}}
    <div class="sort-modal" id="sortAssignModal" aria-hidden="true">
        <div class="sort-dialog sort-dialog-small" role="dialog" aria-modal="true"
             aria-labelledby="sortAssignTitle">
            <div class="sort-dialog-header">
                <div>
                    <p class="sort-eyebrow">PARCEL ASSIGNMENT</p>
                    <h2 id="sortAssignTitle">Assign to Sorting Batch</h2>
                    <p>Select a matching destination batch.</p>
                </div>
                <button type="button" class="sort-close" data-sort-close
                        aria-label="Close">×</button>
            </div>

            <form id="sortAssignForm">
                <div class="sort-selected-info">
                    <span>SELECTED PARCELS</span>
                    <strong id="sortAssignSelected">0 parcels</strong>
                </div>

                <div class="sort-field">
                    <label for="sortAssignBatch">Sorting Batch *</label>
                    <select id="sortAssignBatch" required>
                        <option value="">Select batch</option>
                    </select>
                </div>

                <p class="sort-form-help">
                    Only parcels matching the selected batch destination
                    can be assigned.
                </p>

                <div class="sort-dialog-footer">
                    <button type="button" class="sort-btn sort-btn-outline"
                            data-sort-close>Cancel</button>
                    <button type="submit" class="sort-btn sort-btn-primary">
                        Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- PARCEL DETAILS MODAL --}}
    <div class="sort-modal" id="sortDetailsModal" aria-hidden="true">
        <div class="sort-dialog" role="dialog" aria-modal="true"
             aria-labelledby="sortDetailsTitle">
            <div class="sort-dialog-header">
                <div>
                    <p class="sort-eyebrow">PARCEL INFORMATION</p>
                    <h2 id="sortDetailsTitle">Sorting Details</h2>
                    <p id="sortDetailsSubtitle">Parcel information</p>
                </div>
                <button type="button" class="sort-close" data-sort-close
                        aria-label="Close">×</button>
            </div>

            <div class="sort-detail-grid" id="sortDetailsGrid"></div>

            <div class="sort-detail-note" id="sortDetailsNote"></div>

            <div class="sort-dialog-footer">
                <button type="button" class="sort-btn sort-btn-outline"
                        data-sort-close>Close</button>
                <button type="button" class="sort-btn sort-btn-primary"
                        id="sortDetailsAction">Mark as Sorted</button>
            </div>
        </div>
    </div>

    {{-- CONFIRM SORTING MODAL --}}
    <div class="sort-modal" id="sortConfirmModal" aria-hidden="true">
        <div class="sort-dialog sort-dialog-small" role="dialog" aria-modal="true"
             aria-labelledby="sortConfirmTitle">
            <div class="sort-dialog-header">
                <div>
                    <p class="sort-eyebrow">SORTING CONFIRMATION</p>
                    <h2 id="sortConfirmTitle">Complete Parcel Sorting</h2>
                    <p>Confirm that this parcel has been sorted into its batch.</p>
                </div>
                <button type="button" class="sort-close" data-sort-close
                        aria-label="Close">×</button>
            </div>

            <div class="sort-selected-info">
                <span>TRACKING ID</span>
                <strong id="sortConfirmTracking">—</strong>
            </div>

            <div class="sort-detail-note">
                Once confirmed, this parcel will be marked
                <strong>Sorted</strong> and ready for delivery assignment.
            </div>

            <div class="sort-dialog-footer">
                <button type="button" class="sort-btn sort-btn-outline"
                        data-sort-close>Cancel</button>
                <button type="button" class="sort-btn sort-btn-primary"
                        id="sortConfirmButton">Confirm Sorting</button>
            </div>
        </div>
    </div>

    {{-- BATCH DETAILS MODAL --}}
    <div class="sort-modal" id="sortBatchDetailsModal" aria-hidden="true">
        <div class="sort-dialog" role="dialog" aria-modal="true"
             aria-labelledby="sortBatchDetailsTitle">
            <div class="sort-dialog-header">
                <div>
                    <p class="sort-eyebrow">BATCH INFORMATION</p>
                    <h2 id="sortBatchDetailsTitle">Sorting Batch</h2>
                    <p id="sortBatchDetailsSubtitle">Batch overview</p>
                </div>
                <button type="button" class="sort-close" data-sort-close
                        aria-label="Close">×</button>
            </div>

            <div class="sort-detail-grid" id="sortBatchDetailsGrid"></div>

            <div class="sort-detail-note" id="sortBatchDetailsNote"></div>

            <div class="sort-dialog-footer">
                <button type="button" class="sort-btn sort-btn-outline"
                        data-sort-close>Close</button>
            </div>
        </div>
    </div>

    <div class="sort-toast" id="sortToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/sorting.js') }}" defer></script>
@endpush
