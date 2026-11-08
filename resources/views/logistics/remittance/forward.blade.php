@extends('layouts.logistics')

@section('title', 'Forward COD to Admin | LUMEAR Logistics')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/logistics/remittance.css') }}"
>

<style>
    .cf-form {
        padding: 20px;
        display: grid;
        gap: 12px;
        max-width: 600px;
    }

    .cf-form input {
        padding: 12px;
        border: 1px solid #998484;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
    }

    .cf-pages {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 16px;
    }
</style>
@endpush

@section('content')
@php
    $peso = fn ($minor) =>
        '₱' . number_format(((int) $minor) / 100, 2);

    $labels = [
        'pending_admin' => 'Pending Admin Credit',
        'completed' => 'Forwarded to Admin',
        'rejected' => 'Rejected — Reservation Released',
    ];
@endphp

<div class="remittance-page">
    <div class="remittance-heading">
        <div>
            <p class="remittance-eyebrow">
                FINANCE / COD FORWARDING
            </p>

            <h1>Forward COD to Admin</h1>

            <p>
                Forward verified COD funds to LUMEAR’s
                main admin remittance wallet.
            </p>
        </div>

        <a
            class="remittance-btn remittance-btn-secondary"
            href="{{ route('logistics.remittance.index') }}"
        >
            Rider Remittances
        </a>
    </div>

    @if(session('success'))
        <div
            class="remittance-alert remittance-alert-success"
            role="status"
        >
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div
            class="remittance-alert remittance-alert-error"
            role="alert"
        >
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="remittance-stats">
        <div class="remittance-stat">
            <span>Verified Allocated COD</span>
            <strong>{{ $peso($stats['eligible']) }}</strong>
            <small>Cumulative eligible receipts</small>
        </div>

        <div class="remittance-stat">
            <span>Available to Forward</span>
            <strong>{{ $peso($stats['available']) }}</strong>
            <small>After pending and completed transfers</small>
        </div>

        <div class="remittance-stat">
            <span>Pending Admin Credit</span>
            <strong>{{ $peso($stats['pending']) }}</strong>
            <small>Reserved against duplicate submission</small>
        </div>

        <div class="remittance-stat">
            <span>Forwarded to Admin</span>
            <strong>{{ $peso($stats['completed']) }}</strong>
            <small>Credit confirmed by admin system</small>
        </div>
    </div>

    <div class="remittance-alert">
        Unallocated funds awaiting reconciliation:
        {{ $peso($stats['unallocated']) }}.
        These funds and logistics commissions are excluded
        from forwarding.
    </div>

    <section class="remittance-panel">
        <div class="remittance-panel-heading">
            <div>
                <h2>New Forwarding Request</h2>

                <p>
                    Recipient:
                    LUMEAR Main Admin Remittance Wallet
                </p>
            </div>
        </div>

        <form
            class="cf-form"
            method="POST"
            action="{{ route('logistics.cod-forwarding.store') }}"
        >
            @csrf

            <input
                type="hidden"
                name="request_key"
                value="{{ $requestKey }}"
            >

            <label for="cfAmount">
                Amount in PHP
            </label>

            <input
                id="cfAmount"
                name="amount"
                type="text"
                inputmode="decimal"
                required
                maxlength="13"
                pattern="[0-9]{1,10}([.][0-9]{1,2})?"
                value="{{ old('amount') }}"
                placeholder="Example: 1500.00"
            >

            <p>
                Submitting reserves the amount.
                The request becomes completed only after
                the admin system confirms its wallet credit.
            </p>

            <button
                type="submit"
                class="remittance-btn remittance-btn-primary"
                @disabled($stats['available'] <= 0)
            >
                Submit Forwarding Request
            </button>
        </form>
    </section>

    <section class="remittance-panel">
        <div class="remittance-panel-heading">
            <div>
                <h2>Forwarding History</h2>

                <p>
                    Pending reservations remain held until admin
                    confirms credit or definitively rejects the request.
                </p>
            </div>
        </div>

        <div class="remittance-table-wrap">
            <table class="remittance-table">
                <thead>
                    <tr>
                        <th>Reference / Sources</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Admin Transaction</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($transfers as $transfer)
                        <tr>
                            <td>
                                <strong>
                                    {{ $transfer->reference }}
                                </strong>

                                <details>
                                    <summary>
                                        Source COD receipts
                                    </summary>

                                    <ul>
                                        @foreach(
                                            $items->get(
                                                $transfer->id,
                                                collect()
                                            ) as $item
                                        )
                                            <li>
                                                Receipt
                                                #{{ $item->cod_wallet_entry_id }}
                                                / rider remittance
                                                #{{ $item->rider_remittance_id }}:
                                                {{ $peso($item->amount_minor) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            </td>

                            <td>
                                {{ $peso($transfer->amount_minor) }}
                            </td>

                            <td>
                                {{ $labels[$transfer->status]
                                    ?? $transfer->status }}

                                @if($transfer->rejection_reason)
                                    <small>
                                        {{ $transfer->rejection_reason }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                {{ $transfer->admin_reference
                                    ?? 'Awaiting admin' }}

                                @if($transfer->completed_at)
                                    <small>
                                        {{ $transfer->completed_at }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                {{ $transfer->created_at }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No forwarding requests yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="cf-pages">
            @if($transfers->previousPageUrl())
                <a href="{{ $transfers->previousPageUrl() }}">
                    Previous
                </a>
            @else
                <span></span>
            @endif

            <span>
                Page {{ $transfers->currentPage() }}
                of {{ $transfers->lastPage() }}
            </span>

            @if($transfers->nextPageUrl())
                <a href="{{ $transfers->nextPageUrl() }}">
                    Next
                </a>
            @else
                <span></span>
            @endif
        </div>
    </section>
</div>
@endsection