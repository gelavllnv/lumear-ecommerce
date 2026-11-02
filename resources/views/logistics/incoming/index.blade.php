
@extends('layouts.logistics')

@section('title', 'Incoming Parcels | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/incoming.css') }}">
@endpush

@section('content')
<div class="inc-page" id="incomingApp">

    <header class="inc-heading">
        <div>
            <p class="inc-eyebrow">PARCEL OPERATIONS</p>
            <h1>Incoming Parcels</h1>
            <p class="inc-description">
                Monitor expected shipments, verify parcel arrivals,
                and manage inbound parcels at your sorting center.
            </p>
        </div>

        <div class="inc-heading-actions">
            <button type="button" class="inc-btn inc-btn-outline" id="incRefresh">
                <span>↻</span> Refresh
            </button>
            <button type="button" class="inc-btn inc-btn-primary" id="incRegister">
                <span>＋</span> Register Parcel
            </button>
        </div>
    </header>

    <section class="inc-stats" aria-label="Incoming parcel statistics">
        <article class="inc-stat">
            <div class="inc-stat-icon inc-icon-amber">◷</div>
            <div>
                <span>Awaiting Arrival</span>
                <strong id="incAwaitingCount">0</strong>
                <small>Expected at sorting center</small>
            </div>
        </article>

        <article class="inc-stat">
            <div class="inc-stat-icon inc-icon-green">✓</div>
            <div>
                <span>Received at Hub</span>
                <strong id="incReceivedCount">0</strong>
                <small>Successfully checked in</small>
            </div>
        </article>

        <article class="inc-stat">
            <div class="inc-stat-icon inc-icon-blue">▣</div>
            <div>
                <span>Ready for Sorting</span>
                <strong id="incReadyCount">0</strong>
                <small>Verified incoming parcels</small>
            </div>
        </article>

        <article class="inc-stat">
            <div class="inc-stat-icon inc-icon-red">!</div>
            <div>
                <span>With Discrepancies</span>
                <strong id="incIssueCount">0</strong>
                <small>Requires further checking</small>
            </div>
        </article>
    </section>

    <section class="inc-toolbar">
        <div class="inc-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-4-4"></path>
            </svg>
            <input id="incSearch" type="search"
                   placeholder="Search tracking ID, seller, recipient or destination..."
                   aria-label="Search incoming parcels">
        </div>

        <div class="inc-filter-group">
            <select id="incStatusFilter" aria-label="Filter by status">
                <option value="all">All Status</option>
                <option value="Awaiting Arrival">Awaiting Arrival</option>
                <option value="Received">Received</option>
                <option value="Ready for Sorting">Ready for Sorting</option>
                <option value="Discrepancy">Discrepancy</option>
            </select>

            <select id="incAreaFilter" aria-label="Filter by destination">
                <option value="all">All Areas</option>
            </select>
        </div>
    </section>

    <section class="inc-panel">
        <div class="inc-panel-header">
            <div>
                <p class="inc-eyebrow">INBOUND SHIPMENTS</p>
                <h2>Parcel Receiving Queue</h2>
                <p>Expected parcels and shipments recorded at the sorting center.</p>
            </div>

            <div class="inc-panel-actions">
                <span class="inc-count" id="incVisibleCount">0 parcels</span>
                <button type="button" class="inc-btn inc-btn-outline" id="incExport">
                    Export List
                </button>
            </div>
        </div>

        <div class="inc-table-scroll">
            <table class="inc-table">
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>Seller / Sender</th>
                        <th>Recipient</th>
                        <th>Destination</th>
                        <th>Parcel</th>
                        <th>Expected</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="incTableBody"></tbody>
            </table>
        </div>

        <div class="inc-empty" id="incEmpty" hidden>
            <div class="inc-empty-icon">▢</div>
            <strong>No matching parcels</strong>
            <p>Try another tracking number or change your filters.</p>
        </div>

        <footer class="inc-table-footer">
            <span id="incShowing">Showing 0 parcels</span>
            <div class="inc-pagination">
                <button type="button" id="incPrev">Previous</button>
                <span id="incPageLabel">Page 1 of 1</span>
                <button type="button" id="incNext">Next</button>
            </div>
        </footer>
    </section>

    <div class="inc-bottom-note">
        <span class="inc-demo-dot"></span>
        UI demonstration — parcel data is temporary and is not connected to the
        Seller, Rider, or Buyer databases.
    </div>

    {{-- REGISTER PARCEL MODAL --}}
    <div class="inc-modal" id="incRegisterModal" aria-hidden="true">
        <div class="inc-dialog" role="dialog" aria-modal="true"
             aria-labelledby="incRegisterTitle">

            <div class="inc-dialog-header">
                <div>
                    <p class="inc-eyebrow">INBOUND REGISTRATION</p>
                    <h2 id="incRegisterTitle">Register Incoming Parcel</h2>
                    <p>Add an expected shipment to the receiving queue.</p>
                </div>
                <button type="button" class="inc-close" data-close-modal
                        aria-label="Close">×</button>
            </div>

            <form id="incRegisterForm">
                <div class="inc-form-grid">
                    <div class="inc-field">
                        <label for="incTracking">Tracking ID *</label>
                        <input id="incTracking" name="tracking" required
                               maxlength="50" pattern="[A-Za-z0-9-]+"
                               placeholder="LMR-IN-241001">
                    </div>

                    <div class="inc-field">
                        <label for="incSeller">Seller / Sender *</label>
                        <input id="incSeller" name="seller" required maxlength="100"
                               placeholder="Store or seller name">
                    </div>

                    <div class="inc-field">
                        <label for="incRecipient">Recipient *</label>
                        <input id="incRecipient" name="recipient" required maxlength="100"
                               placeholder="Recipient full name">
                    </div>

                    <div class="inc-field">
                        <label for="incContact">Contact Number *</label>
                        <input id="incContact" name="contact" required
                               inputmode="numeric" pattern="09[0-9]{9}"
                               maxlength="11" placeholder="09XXXXXXXXX">
                    </div>

                    <div class="inc-field">
                        <label for="incDestination">Destination *</label>
                        <input id="incDestination" name="destination" required
                               maxlength="120" placeholder="Santa Cruz, Laguna">
                    </div>

                    <div class="inc-field">
                        <label for="incType">Parcel Type *</label>
                        <select id="incType" name="type" required>
                            <option value="Standard">Standard</option>
                            <option value="Fragile">Fragile</option>
                            <option value="Documents">Documents</option>
                            <option value="Bulky">Bulky</option>
                        </select>
                    </div>

                    <div class="inc-field">
                        <label for="incWeight">Weight (kg) *</label>
                        <input id="incWeight" name="weight" type="number"
                               min="0.01" max="1000" step="0.01" required
                               placeholder="1.50">
                    </div>

                    <div class="inc-field">
                        <label for="incExpected">Expected Arrival *</label>
                        <input id="incExpected" name="expected" type="date" required>
                    </div>

                    <div class="inc-field inc-field-full">
                        <label for="incNotes">Handling Instructions</label>
                        <textarea id="incNotes" name="notes" rows="3"
                                  maxlength="500"
                                  placeholder="Optional handling instructions"></textarea>
                    </div>
                </div>

                <div class="inc-dialog-footer">
                    <button type="button" class="inc-btn inc-btn-outline"
                            data-close-modal>Cancel</button>
                    <button type="submit" class="inc-btn inc-btn-primary">
                        Register Parcel
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- PARCEL DETAILS MODAL --}}
    <div class="inc-modal" id="incDetailsModal" aria-hidden="true">
        <div class="inc-dialog" role="dialog" aria-modal="true"
             aria-labelledby="incDetailsTitle">
            <div class="inc-dialog-header">
                <div>
                    <p class="inc-eyebrow">PARCEL INFORMATION</p>
                    <h2 id="incDetailsTitle">Parcel Details</h2>
                    <p id="incDetailsSubtitle">Inbound shipment information</p>
                </div>
                <button type="button" class="inc-close" data-close-modal
                        aria-label="Close">×</button>
            </div>

            <div class="inc-detail-grid" id="incDetailsGrid"></div>

            <div class="inc-detail-note" id="incDetailsNote"></div>

            <div class="inc-dialog-footer">
                <button type="button" class="inc-btn inc-btn-outline"
                        data-close-modal>Close</button>
                <button type="button" class="inc-btn inc-btn-primary"
                        id="incDetailsAction">Receive Parcel</button>
            </div>
        </div>
    </div>

    {{-- RECEIVE / VERIFY / DISCREPANCY MODAL --}}
    <div class="inc-modal" id="incActionModal" aria-hidden="true">
        <div class="inc-dialog inc-dialog-small" role="dialog" aria-modal="true"
             aria-labelledby="incActionTitle">
            <div class="inc-dialog-header">
                <div>
                    <p class="inc-eyebrow">PARCEL OPERATIONS</p>
                    <h2 id="incActionTitle">Update Parcel</h2>
                    <p id="incActionSubtitle">Confirm the selected action.</p>
                </div>
                <button type="button" class="inc-close" data-close-modal
                        aria-label="Close">×</button>
            </div>

            <form id="incActionForm">
                <div class="inc-selected-parcel">
                    <span>SELECTED PARCEL</span>
                    <strong id="incSelectedTracking">—</strong>
                </div>

                <div class="inc-field" id="incConditionField">
                    <label for="incCondition">Parcel Condition</label>
                    <select id="incCondition">
                        <option value="Good condition">Good condition</option>
                        <option value="Damaged packaging">Damaged packaging</option>
                        <option value="Incorrect parcel information">
                            Incorrect parcel information
                        </option>
                        <option value="Other concern">Other concern</option>
                    </select>
                </div>

                <div class="inc-field" id="incActionNotesField">
                    <label for="incActionNotes">Remarks</label>
                    <textarea id="incActionNotes" rows="3"
                              maxlength="500"
                              placeholder="Add receiving or discrepancy remarks"></textarea>
                </div>

                <div class="inc-dialog-footer">
                    <button type="button" class="inc-btn inc-btn-outline"
                            data-close-modal>Cancel</button>
                    <button type="submit" class="inc-btn inc-btn-primary"
                            id="incConfirmAction">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    {{-- TOAST --}}
    <div class="inc-toast" id="incToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/incoming.js') }}" defer></script>
@endpush
