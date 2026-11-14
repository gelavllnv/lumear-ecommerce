@extends('layouts.logistics')

@section('title', 'COD Remittance | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/remittance.css') }}">
@endpush

@section('content')

@php
    $peso = fn ($minor) => '₱' . number_format(((int) $minor) / 100, 2);

    $statusLabel = fn ($status) => match ($status) {
        'pending_verification' => 'Pending Verification',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        default => ucwords(str_replace('_', ' ', $status)),
    };
@endphp

<div class="remittance-page">

    <div class="remittance-heading">
        <div>
            <p class="remittance-eyebrow">FINANCE / COD MANAGEMENT</p>
            <h1>COD Remittance</h1>
            <p>
                Monitor rider COD balances, cash handoffs,
                verification, and discrepancies.
            </p>
        </div>

        <div class="remittance-heading-label">
            <span class="remittance-heading-dot"></span>
            COD Handoff Management
        </div>
    </div>

    @if(session('success'))
        <div class="remittance-alert remittance-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="remittance-alert remittance-alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="remittance-stats">

        <div class="remittance-stat">
            <span>Total Expected COD</span>
            <strong>{{ $peso($stats['expected']) }}</strong>
            <small>Recorded COD obligations</small>
        </div>

        <div class="remittance-stat">
            <span>Verified Remittances</span>
            <strong>{{ $peso($stats['settled']) }}</strong>
            <small>Applied to COD balances</small>
        </div>

        <div class="remittance-stat">
            <span>Outstanding COD</span>
            <strong>{{ $peso($stats['outstanding']) }}</strong>
            <small>Not yet settled</small>
        </div>

        <div class="remittance-stat remittance-stat-warning">
            <span>Overdue COD</span>
            <strong>{{ $peso($stats['overdue']) }}</strong>
            <small>Past the remittance deadline</small>
        </div>

    </div>

    <div class="remittance-mini-stats">
        <div>
            <span>Pending Verification</span>
            <strong>{{ $stats['pending_count'] }}</strong>
        </div>

        <div>
            <span>Open Discrepancies</span>
            <strong>{{ $stats['open_discrepancies'] }}</strong>
        </div>
    </div>

    <section class="remittance-panel">

        <div class="remittance-panel-heading">
            <div>
                <h2>Rider Remittance Submissions</h2>
                <p>
                    Confirm each cash handoff only after the rider has given
                    the money to your hub.
                </p>
            </div>
        </div>

        <div class="remittance-table-wrap">
            <table class="remittance-table">
                <thead>
                    <tr>
                        <th>Remittance</th>
                        <th>Rider</th>
                        <th>Payment Method</th>
                        <th>Amount</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($remittances as $remittance)
                        <tr>
                            <td>
                                <strong>
                                    {{ $remittance->remittance_number }}
                                </strong>
                                <small>
                                    Ref: {{ $remittance->transaction_reference }}
                                </small>
                            </td>

                            <td>{{ $remittance->rider_name }}</td>

                            <td>
                                @if($remittance->payment_method === 'other' && str_starts_with($remittance->transaction_reference, 'HANDOFF-'))
                                    Cash Handoff
                                @else
                                    {{ strtoupper(str_replace('_', ' ', $remittance->payment_method)) }}
                                @endif
                                @if($remittance->payment_provider)
                                    <small>
                                        {{ $remittance->payment_provider }}
                                    </small>
                                @endif
                            </td>

                            <td class="remittance-money">
                                {{ $peso($remittance->submitted_amount_minor) }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($remittance->submitted_at)->timezone('Asia/Manila')->format('M d, Y') }}
                                <small>
                                    {{ \Carbon\Carbon::parse($remittance->submitted_at)->timezone('Asia/Manila')->format('h:i A') }}
                                </small>
                            </td>

                            <td>
                                <span class="remittance-status status-{{ $remittance->status }}">
                                    {{ $statusLabel($remittance->status) }}
                                </span>
                            </td>

                            <td>
                                <div class="remittance-actions">
                                    @if($remittance->receipt_path)
                                        <a
                                            href="{{ route('logistics.remittance.receipt', $remittance->id) }}"
                                            class="remittance-btn remittance-btn-secondary"
                                        >
                                            Receipt
                                        </a>
                                    @endif

                                    @if($remittance->status === 'pending_verification')
                                        <button
                                            type="button"
                                            class="remittance-btn remittance-btn-primary"
                                            data-open-verify="{{ $remittance->id }}"
                                        >
                                            Review
                                        </button>
                                    @else
                                        <span class="remittance-muted">Reviewed</span>
                                    @endif
                                </div>

                                @if($remittance->status === 'pending_verification')
                                    <div
                                        class="remittance-review"
                                        id="remittance-review-{{ $remittance->id }}"
                                        hidden
                                    >
                                        <p>
                                            Confirm that
                                            <strong>{{ $peso($remittance->submitted_amount_minor) }}</strong>
                                            was physically handed to your hub by this rider.
                                        </p>

                                        <form
                                            method="POST"
                                            action="{{ route('logistics.remittance.verify', $remittance->id) }}"
                                            class="remittance-review-form"
                                            data-confirm-verify
                                        >
                                            @csrf

                                            <textarea
                                                name="remarks"
                                                rows="2"
                                                maxlength="1000"
                                                placeholder="Verification remarks (optional)"
                                            ></textarea>

                                            <label class="remittance-confirm">
                                                <input
                                                    type="checkbox"
                                                    name="funds_confirmed"
                                                    value="1"
                                                    required
                                                >
                                                I confirm that I physically received this cash for the hub.
                                            </label>

                                            <button
                                                type="submit"
                                                class="remittance-btn remittance-btn-success"
                                            >
                                                Confirm Cash Handoff
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('logistics.remittance.reject', $remittance->id) }}"
                                            class="remittance-review-form"
                                            data-confirm-reject
                                        >
                                            @csrf

                                            <textarea
                                                name="reason"
                                                rows="2"
                                                minlength="5"
                                                maxlength="1000"
                                                required
                                                placeholder="Reason for rejection"
                                            ></textarea>

                                            <button
                                                type="submit"
                                                class="remittance-btn remittance-btn-danger"
                                            >
                                                Reject Transfer
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="remittance-empty">
                                No digital remittance submissions yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="remittance-pagination">
            {{ $remittances->links() }}
        </div>

    </section>

    <section class="remittance-panel">

        <div class="remittance-panel-heading">
            <div>
                <h2>Rider COD Balances</h2>
                <p>
                    Outstanding amounts owed by each rider.
                </p>
            </div>
        </div>

        <div class="remittance-table-wrap">
            <table class="remittance-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Expected COD</th>
                        <th>Settled</th>
                        <th>Outstanding</th>
                        <th>Restriction</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($riderBalances as $rider)
                        <tr>
                            <td>
                                <strong>{{ $rider->rider_name }}</strong>
                                <small>Rider #{{ $rider->id }}</small>
                            </td>

                            <td>{{ $peso($rider->expected_minor) }}</td>

                            <td>{{ $peso($rider->settled_minor) }}</td>

                            <td class="remittance-money">
                                {{ $peso($rider->outstanding_minor) }}
                            </td>

                            <td>
                                @if($rider->restricted)
                                    <span class="remittance-status status-rejected">
                                        Restricted
                                    </span>
                                @else
                                    <span class="remittance-status status-verified">
                                        No Restriction
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="remittance-empty">
                                No riders found for this logistics provider.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </section>

    <section class="remittance-panel">

        <div class="remittance-panel-heading">
            <div>
                <h2>Discrepancy Reports</h2>
                <p>
                    Unmatched funds, shortages, and other
                    reconciliation issues.
                </p>
            </div>
        </div>

        <div class="remittance-table-wrap">
            <table class="remittance-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Type</th>
                        <th>Difference</th>
                        <th>Status</th>
                        <th>Reported</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($discrepancies as $discrepancy)
                        <tr>
                            <td>{{ $discrepancy->rider_name }}</td>

                            <td>
                                {{ ucfirst($discrepancy->type) }}
                            </td>

                            <td class="remittance-money">
                                {{ $peso($discrepancy->difference_minor) }}
                            </td>

                            <td>
                                <span class="remittance-status status-{{ $discrepancy->status }}">
                                    {{ ucwords(str_replace('_', ' ', $discrepancy->status)) }}
                                </span>
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($discrepancy->created_at)->timezone('Asia/Manila')->format('M d, Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="remittance-empty">
                                No discrepancy reports found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </section>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/logistics/remittance.js') }}"></script>
@endpush
