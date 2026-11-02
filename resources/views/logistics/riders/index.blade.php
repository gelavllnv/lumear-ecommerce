
@extends('layouts.logistics')

@section('title', 'Rider List | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/riders.css') }}">
@endpush

@section('content')
<div class="rm-page" id="riderManagementApp" data-page="riders">
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">LOGISTICS / RIDER MANAGEMENT</p>
            <h1>Rider List</h1>
            <p>Manage approved delivery riders, monitor their availability, and review account status.</p>
        </div>
        <div class="rm-header-actions">
            <a class="rm-button rm-button-outline" href="{{ route('logistics.riders.applications') }}">Rider Applications</a>
            <button type="button" class="rm-button rm-button-primary" id="rmExport">Export Rider List</button>
        </div>
    </header>

    <section class="rm-stats">
        <article class="rm-stat">
            <span class="rm-stat-icon blue">♙</span>
            <div><small>Total Riders</small><strong id="rmTotal">0</strong><span>Approved accounts</span></div>
        </article>
        <article class="rm-stat">
            <span class="rm-stat-icon green">✓</span>
            <div><small>Available</small><strong id="rmAvailable">0</strong><span>Ready for assignments</span></div>
        </article>
        <article class="rm-stat">
            <span class="rm-stat-icon amber">◷</span>
            <div><small>Offline</small><strong id="rmOffline">0</strong><span>Not currently available</span></div>
        </article>
        <article class="rm-stat">
            <span class="rm-stat-icon red">!</span>
            <div><small>Suspended</small><strong id="rmSuspended">0</strong><span>Temporarily restricted</span></div>
        </article>
    </section>

    <section class="rm-panel">
        <div class="rm-panel-header">
            <div>
                <p class="rm-eyebrow">RIDER DIRECTORY</p>
                <h2>Registered Delivery Riders</h2>
                <p>Only approved riders appear in this directory.</p>
            </div>
            <span class="rm-counter" id="rmCount">0 riders</span>
        </div>

        <div class="rm-toolbar">
            <input type="search" id="rmSearch" placeholder="Search rider ID, name, email or municipality..." aria-label="Search riders">
            <select id="rmFilter" aria-label="Filter rider status">
                <option value="All">All Riders</option>
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
                        <th>Rider</th>
                        <th>Assigned Area</th>
                        <th>Vehicle</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="rmTableBody"></tbody>
            </table>
        </div>

        <div class="rm-empty" id="rmEmpty" hidden>No riders match your search.</div>
        <div class="rm-footer">
            <span id="rmShowing">Showing 0 riders</span>
            <div class="rm-pages">
                <button type="button" id="rmPrev">Previous</button>
                <span id="rmPageLabel">Page 1 of 1</span>
                <button type="button" id="rmNext">Next</button>
            </div>
        </div>
    </section>

    <p class="rm-note">Demo mode: rider availability is managed manually on this page. It does not reflect live Flutter app presence or existing delivery assignments.</p>

    <div class="rm-modal" id="rmDetailModal" aria-hidden="true">
        <div class="rm-dialog" role="dialog" aria-modal="true" aria-labelledby="rmDetailTitle">
            <div class="rm-dialog-header">
                <div>
                    <p class="rm-eyebrow">RIDER PROFILE</p>
                    <h2 id="rmDetailTitle">Rider Information</h2>
                    <p id="rmDetailSubtitle"></p>
                </div>
                <button type="button" class="rm-close" data-rm-close aria-label="Close">×</button>
            </div>

            <div class="rm-detail-grid" id="rmDetailGrid"></div>

            <div class="rm-dialog-footer">
                <button type="button" class="rm-button rm-button-outline" data-rm-close>Close</button>
                <button type="button" class="rm-button rm-button-primary" id="rmManageFromDetail">Manage Status</button>
            </div>
        </div>
    </div>

    <div class="rm-modal" id="rmStatusModal" aria-hidden="true">
        <div class="rm-dialog rm-dialog-small" role="dialog" aria-modal="true" aria-labelledby="rmStatusTitle">
            <div class="rm-dialog-header">
                <div>
                    <p class="rm-eyebrow">RIDER ACCOUNT</p>
                    <h2 id="rmStatusTitle">Manage Rider Status</h2>
                    <p id="rmStatusSubtitle"></p>
                </div>
                <button type="button" class="rm-close" data-rm-close aria-label="Close">×</button>
            </div>

            <form id="rmStatusForm">
                <label class="rm-label" for="rmNewStatus">Rider Status</label>
                <select id="rmNewStatus" required>
                    <option value="Available">Available</option>
                    <option value="Offline">Offline</option>
                    <option value="Suspended">Suspended</option>
                </select>

                <label class="rm-label rm-label-spaced" for="rmStatusReason">Remarks</label>
                <textarea id="rmStatusReason" rows="4" maxlength="500" placeholder="Optional remarks; required for suspension..."></textarea>

                <div class="rm-dialog-footer">
                    <button type="button" class="rm-button rm-button-outline" data-rm-close>Cancel</button>
                    <button type="submit" class="rm-button rm-button-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="rm-toast" id="rmToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/riders.js') }}" defer></script>
@endpush
