
@extends('layouts.logistics')

@section('title', 'Rider Applications | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/riders.css') }}">
@endpush

@section('content')
<div class="rm-page" id="riderManagementApp" data-page="applications">
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">LOGISTICS / RIDER MANAGEMENT</p>
            <h1>Rider Applications</h1>
            <p>Review registration details, verify documents, and manage rider applications for your sorting center.</p>
        </div>
        <div class="rm-header-actions">
            <a class="rm-button rm-button-outline" href="{{ route('logistics.riders.index') }}">View Rider List</a>
            <button class="rm-button rm-button-primary" type="button" id="rmExport">Export Applications</button>
        </div>
    </header>

    <section class="rm-stats">
        <article class="rm-stat">
            <span class="rm-stat-icon amber">◷</span>
            <div><small>Pending Review</small><strong id="rmPending">0</strong><span>Awaiting verification</span></div>
        </article>
        <article class="rm-stat">
            <span class="rm-stat-icon blue">▣</span>
            <div><small>Total Applications</small><strong id="rmTotal">0</strong><span>All submitted records</span></div>
        </article>
        <article class="rm-stat">
            <span class="rm-stat-icon green">✓</span>
            <div><small>Approved</small><strong id="rmApproved">0</strong><span>Accepted applications</span></div>
        </article>
        <article class="rm-stat">
            <span class="rm-stat-icon red">×</span>
            <div><small>Rejected</small><strong id="rmRejected">0</strong><span>Declined applications</span></div>
        </article>
    </section>

    <section class="rm-panel">
        <div class="rm-panel-header">
            <div>
                <p class="rm-eyebrow">APPLICATION QUEUE</p>
                <h2>Rider Registration Requests</h2>
                <p>Review each application before approving or rejecting it.</p>
            </div>
            <span class="rm-counter" id="rmCount">0 applications</span>
        </div>

        <div class="rm-toolbar">
            <input id="rmSearch" type="search" placeholder="Search name, email, application ID or municipality..." aria-label="Search applications">
            <select id="rmFilter" aria-label="Filter application status">
                <option value="All">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>

        <div class="rm-table-scroll">
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <th>Applicant</th>
                        <th>Municipality</th>
                        <th>Vehicle</th>
                        <th>Date Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="rmTableBody"></tbody>
            </table>
        </div>

        <div class="rm-empty" id="rmEmpty" hidden>No applications match your search.</div>
        <div class="rm-footer">
            <span id="rmShowing">Showing 0 applications</span>
            <div class="rm-pages">
                <button type="button" id="rmPrev">Previous</button>
                <span id="rmPageLabel">Page 1 of 1</span>
                <button type="button" id="rmNext">Next</button>
            </div>
        </div>
    </section>

    <p class="rm-note">Demo mode: applicant records and document verification are simulated. Real identity documents must be checked securely through the backend before approval.</p>

    <div class="rm-modal" id="rmDetailModal" aria-hidden="true">
        <div class="rm-dialog" role="dialog" aria-modal="true" aria-labelledby="rmDetailTitle">
            <div class="rm-dialog-header">
                <div>
                    <p class="rm-eyebrow">APPLICATION REVIEW</p>
                    <h2 id="rmDetailTitle">Applicant Information</h2>
                    <p id="rmDetailSubtitle"></p>
                </div>
                <button type="button" class="rm-close" data-rm-close aria-label="Close">×</button>
            </div>
            <div class="rm-detail-grid" id="rmDetailGrid"></div>
            <h3 class="rm-subheading">Submitted Documents</h3>
            <div class="rm-documents" id="rmDocuments"></div>
            <p class="rm-note">Document labels are sample placeholders, not uploaded files. No actual document verification occurs in this demo.</p>
            <div class="rm-dialog-footer">
                <button type="button" class="rm-button rm-button-outline" data-rm-close>Close</button>
                <button type="button" class="rm-button rm-button-danger" id="rmRejectFromDetail">Reject</button>
                <button type="button" class="rm-button rm-button-primary" id="rmApproveFromDetail">Approve Rider</button>
            </div>
        </div>
    </div>

    <div class="rm-modal" id="rmDecisionModal" aria-hidden="true">
        <div class="rm-dialog rm-dialog-small" role="dialog" aria-modal="true" aria-labelledby="rmDecisionTitle">
            <div class="rm-dialog-header">
                <div>
                    <p class="rm-eyebrow">APPLICATION DECISION</p>
                    <h2 id="rmDecisionTitle">Review Application</h2>
                    <p id="rmDecisionSubtitle"></p>
                </div>
                <button type="button" class="rm-close" data-rm-close aria-label="Close">×</button>
            </div>
            <form id="rmDecisionForm">
                <label class="rm-label" for="rmDecisionReason">Review Remarks</label>
                <textarea id="rmDecisionReason" rows="4" maxlength="500" placeholder="Enter remarks or reason for rejection..."></textarea>
                <div class="rm-dialog-footer">
                    <button type="button" class="rm-button rm-button-outline" data-rm-close>Cancel</button>
                    <button type="submit" class="rm-button rm-button-primary" id="rmDecisionSubmit">Confirm</button>
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
