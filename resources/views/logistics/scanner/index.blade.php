@extends('layouts.logistics')

@section('title', 'Waybill Scanner | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/scanner.css') }}">
@endpush

@section('content')
<div class="lqs-page" id="lumearQrScanner">

    <div class="lqs-heading">
        <div>
            <p class="lqs-eyebrow">LOGISTICS / PARCEL OPERATIONS</p>
            <h1>Waybill QR Scanner</h1>
            <p>
                Scan and verify parcels using their
                official LUMEAR waybill QR codes.
            </p>
        </div>
        <span class="lqs-heading-badge">LIVE SHIPMENT LOOKUP</span>
    </div>

    <div class="lqs-grid">

        <section class="lqs-card">
            <div class="lqs-card-header">
                <div>
                    <span class="lqs-section-label">01 / SCANNING</span>
                    <h2>Scan Waybill</h2>
                    <p>Position the QR code inside the camera frame.</p>
                </div>
            </div>

            <div class="lqs-camera-frame">
                <div id="lqsReader"></div>

                <div class="lqs-camera-placeholder"
                     id="lqsPlaceholder">
                    <div class="lqs-scan-icon">▦</div>
                    <strong>Camera Ready</strong>
                    <p>Start the scanner to activate your camera.</p>
                </div>
            </div>

            <div class="lqs-camera-actions">
                <button type="button"
                        class="lqs-btn lqs-btn-primary"
                        id="lqsStart">
                    Start Camera
                </button>

                <button type="button"
                        class="lqs-btn lqs-btn-outline"
                        id="lqsStop"
                        disabled>
                    Stop Camera
                </button>
            </div>

            <div class="lqs-divider">
                <span>OR ENTER TRACKING CODE</span>
            </div>

            <form id="lqsManualForm">
                <label for="lqsTrackingInput">
                    Waybill Tracking Number
                </label>

                <div class="lqs-input-row">
                    <input
                        type="text"
                        id="lqsTrackingInput"
                        placeholder="Enter tracking number"
                        maxlength="255"
                        autocomplete="off"
                        required
                    >

                    <button type="submit"
                            class="lqs-btn lqs-btn-primary"
                            id="lqsSearch">
                        Verify
                    </button>
                </div>
            </form>

            <div class="lqs-hint">
                <strong>How it works</strong>
                <p>
                    The scanned code is checked against shipments
                    assigned to your Logistics provider.
                    Scanning alone does not change parcel status.
                </p>
            </div>
        </section>

        <section class="lqs-card">
            <div class="lqs-card-header">
                <div>
                    <span class="lqs-section-label">02 / VERIFICATION</span>
                    <h2>Parcel Information</h2>
                    <p>Live details from the LUMEAR database.</p>
                </div>
            </div>

            <div class="lqs-result-empty" id="lqsEmpty">
                <div class="lqs-empty-icon">▤</div>
                <h3>Waiting for a waybill</h3>
                <p>
                    Scan a QR code or enter a tracking number
                    to retrieve parcel details.
                </p>
            </div>

            <div class="lqs-result" id="lqsResult" hidden>
                <div class="lqs-verified">
                    <span class="lqs-verified-icon">✓</span>
                    <div>
                        <strong>Waybill Verified</strong>
                        <p>Shipment found in your Logistics records.</p>
                    </div>
                </div>

                <div class="lqs-tracking-block">
                    <span>TRACKING NUMBER</span>
                    <strong id="lqsResultTracking">—</strong>
                </div>

                <div class="lqs-detail-grid">
                    <div>
                        <span>Shipment ID</span>
                        <strong id="lqsResultId">—</strong>
                    </div>

                    <div>
                        <span>Current Status</span>
                        <strong id="lqsResultStatus">—</strong>
                    </div>

                    <div>
                        <span>Assigned Rider ID</span>
                        <strong id="lqsResultRider">—</strong>
                    </div>

                    <div>
                        <span>Seller Order ID</span>
                        <strong id="lqsResultOrder">—</strong>
                    </div>

                    <div>
                        <span>Shipment Created</span>
                        <strong id="lqsResultCreated">—</strong>
                    </div>

                    <div>
                        <span>Last Updated</span>
                        <strong id="lqsResultUpdated">—</strong>
                    </div>
                </div>

                <div class="lqs-readonly">
                    This verification is read-only.
                    Use the appropriate Logistics module
                    to process the parcel.
                </div>
            </div>

            <div class="lqs-error" id="lqsError" hidden></div>
        </section>
    </div>

    <section class="lqs-card lqs-history-card">
        <div class="lqs-card-header">
            <div>
                <span class="lqs-section-label">03 / SESSION ACTIVITY</span>
                <h2>Recent Scans</h2>
                <p>Waybills verified during this page session.</p>
            </div>
            <button type="button"
                    class="lqs-btn lqs-btn-outline"
                    id="lqsClearHistory">
                Clear List
            </button>
        </div>

        <div class="lqs-history-list" id="lqsHistory">
            <div class="lqs-history-empty">
                No waybills scanned yet.
            </div>
        </div>
    </section>

    <div class="lqs-toast" id="lqsToast" role="status"></div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"
        defer></script>
<script src="{{ asset('js/logistics/scanner.js') }}"
        defer></script>
@endpush
