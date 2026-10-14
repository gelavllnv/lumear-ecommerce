@extends('layouts.logistics')

@section('title', $pageTitle . ' — LUMEAR Logistics')

@section('content')
<link rel="stylesheet" href="{{ asset('css/logistics/operations.css') }}">

<div class="ops-page" id="operationsApp" data-module="{{ $module }}">

    <div class="ops-heading">
        <div>
            <span class="ops-eyebrow">LUMEAR / LOGISTICS OPERATIONS</span>
            <h1>{{ $pageTitle }}</h1>
            <p id="opsSubtitle">Manage parcels throughout the logistics process.</p>
        </div>

        <button type="button" class="ops-button ops-button-outline" id="opsReset">
            <span>↻</span> Reset Demo Data
        </button>
    </div>

    <div class="ops-flow">
        <a href="{{ route('logistics.incoming.index') }}"
           class="{{ $module === 'incoming' ? 'active' : '' }}">
            <span>01</span> Incoming
        </a>
        <span class="ops-flow-arrow">→</span>
        <a href="{{ route('logistics.sorting.index') }}"
           class="{{ $module === 'sorting' ? 'active' : '' }}">
            <span>02</span> Sorting
        </a>
        <span class="ops-flow-arrow">→</span>
        <a href="{{ route('logistics.assignment.index') }}"
           class="{{ $module === 'assignment' ? 'active' : '' }}">
            <span>03</span> Assignment
        </a>
        <span class="ops-flow-arrow">→</span>
        <a href="{{ route('logistics.monitoring.index') }}"
           class="{{ $module === 'monitoring' ? 'active' : '' }}">
            <span>04</span> Monitoring
        </a>
    </div>

    <div class="ops-stats">
        <div class="ops-stat">
            <span class="ops-stat-icon">▣</span>
            <div>
                <span id="opsStatLabel1">Total Parcels</span>
                <strong id="opsStat1">0</strong>
                <small>Current demo records</small>
            </div>
        </div>

        <div class="ops-stat">
            <span class="ops-stat-icon">◷</span>
            <div>
                <span id="opsStatLabel2">Awaiting Action</span>
                <strong id="opsStat2">0</strong>
                <small>Requires processing</small>
            </div>
        </div>

        <div class="ops-stat">
            <span class="ops-stat-icon">✓</span>
            <div>
                <span id="opsStatLabel3">Processed</span>
                <strong id="opsStat3">0</strong>
                <small>Completed at this stage</small>
            </div>
        </div>

        <div class="ops-stat">
            <span class="ops-stat-icon">↗</span>
            <div>
                <span id="opsStatLabel4">Active Deliveries</span>
                <strong id="opsStat4">0</strong>
                <small>Currently with riders</small>
            </div>
        </div>
    </div>

    <section class="ops-panel">
        <div class="ops-panel-heading">
            <div>
                <span class="ops-eyebrow" id="opsTableEyebrow">PARCEL MANAGEMENT</span>
                <h2 id="opsTableTitle">Parcel Records</h2>
                <p id="opsTableDescription">View and process parcels.</p>
            </div>
            <span class="ops-count"><strong id="opsVisibleCount">0</strong> records</span>
        </div>

        <div class="ops-toolbar">
            <label class="ops-search">
                <span>⌕</span>
                <input type="search" id="opsSearch"
                       placeholder="Search parcel, seller, buyer, destination...">
            </label>

            <select id="opsStatusFilter">
                <option value="all">All statuses</option>
            </select>
        </div>

        <div class="ops-table-scroll">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>Seller / Buyer</th>
                        <th>Destination</th>
                        <th>Parcels</th>
                        <th>Current Status</th>
                        <th>Rider</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="opsTableBody"></tbody>
            </table>
        </div>

        <div class="ops-empty" id="opsEmpty">
            <span>▧</span>
            <h3>No parcels found</h3>
            <p>There are no parcels matching your filters or this stage.</p>
        </div>
    </section>

    <div class="ops-demo-note">
        <strong>Development preview</strong>
        Changes are stored only in this browser. No MySQL records, seller notifications,
        or driver-app tasks are created yet.
    </div>
</div>

<div class="ops-overlay" id="opsOverlay"></div>

<aside class="ops-drawer" id="opsDrawer" aria-hidden="true" aria-label="Parcel details">
    <div class="ops-drawer-head">
        <div>
            <span class="ops-eyebrow">PARCEL INFORMATION</span>
            <h2 id="opsDrawerTitle">Parcel Details</h2>
        </div>
        <button type="button" id="opsClose" class="ops-close" aria-label="Close">×</button>
    </div>

    <div class="ops-drawer-body">
        <div class="ops-detail-status" id="opsDrawerStatus"></div>

        <div class="ops-detail-section">
            <h3>Shipment Information</h3>
            <div class="ops-detail-grid">
                <div><span>Tracking ID</span><strong id="opsDetailId"></strong></div>
                <div><span>Parcel Quantity</span><strong id="opsDetailQuantity"></strong></div>
                <div><span>Seller</span><strong id="opsDetailSeller"></strong></div>
                <div><span>Buyer</span><strong id="opsDetailBuyer"></strong></div>
                <div class="wide"><span>Destination</span><strong id="opsDetailDestination"></strong></div>
            </div>
        </div>

        <div class="ops-detail-section">
            <h3>Logistics Information</h3>
            <div class="ops-detail-grid">
                <div><span>Sorting Zone</span><strong id="opsDetailZone"></strong></div>
                <div><span>Assigned Rider</span><strong id="opsDetailRider"></strong></div>
            </div>
        </div>

        <div class="ops-detail-section">
            <h3>Processing History</h3>
            <div class="ops-history" id="opsHistory"></div>
        </div>

        <div class="ops-detail-section" id="opsActionSection">
            <h3 id="opsActionHeading">Process Parcel</h3>
            <p id="opsActionHelp" class="ops-help"></p>

            <div id="opsZoneField" class="ops-field">
                <label for="opsZone">Destination sorting zone</label>
                <select id="opsZone">
                    <option value="">Select sorting zone</option>
                    <option value="Santa Cruz Zone">Santa Cruz Zone</option>
                    <option value="Pila Zone">Pila Zone</option>
                    <option value="Victoria Zone">Victoria Zone</option>
                    <option value="Pagsanjan Zone">Pagsanjan Zone</option>
                    <option value="Other Laguna Zone">Other Laguna Zone</option>
                </select>
            </div>

            <div id="opsRiderField" class="ops-field">
                <label for="opsRider">Available delivery rider</label>
                <select id="opsRider">
                    <option value="">Select rider</option>
                    <option value="Juan Reyes">Juan Reyes — Santa Cruz</option>
                    <option value="Andrea Santos">Andrea Santos — Victoria</option>
                    <option value="Daniel Perez">Daniel Perez — Pagsanjan</option>
                    <option value="Miguel Cruz">Miguel Cruz — Pila</option>
                </select>
            </div>

            <div id="opsDeliveryField" class="ops-field">
                <label for="opsDeliveryStatus">Update delivery status</label>
                <select id="opsDeliveryStatus">
                    <option value="">Select next status</option>
                </select>
            </div>

            <p class="ops-validation" id="opsValidation" role="alert"></p>

            <button type="button" class="ops-button ops-button-primary"
                    id="opsConfirmAction">
                Confirm Action
            </button>
        </div>
    </div>
</aside>

<div class="ops-toast" id="opsToast" role="status"></div>

<script src="{{ asset('js/logistics/operations.js') }}"></script>
@endsection