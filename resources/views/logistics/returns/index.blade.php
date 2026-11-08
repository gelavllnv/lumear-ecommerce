@extends('layouts.logistics')

@section('title', 'Returns | LUMEAR Logistics')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/logistics/riders.css') }}"
>

<style>
    .lr-box,
    .lr-form {
        padding: 16px;
    }

    .lr-form {
        display: grid;
        gap: 9px;
        min-width: 240px;
    }

    .lr-form input,
    .lr-form select,
    .lr-form textarea {
        width: 100%;
        padding: 9px;
        border: 1px solid #998484;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
    }

    .lr-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 15px;
        margin: 20px 0;
    }

    .lr-stat {
        padding: 18px;
        border: 1px solid #503b3e;
        border-radius: 12px;
    }

    .lr-stat strong {
        display: block;
        font-size: 28px;
    }

    .lr-pages {
        padding: 16px;
        display: flex;
        justify-content: space-between;
    }

    .lr-small {
        font-size: 12px;
        opacity: .8;
    }

    @media (max-width: 900px) {
        .lr-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
@endpush

@section('content')
<div class="rm-page">
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">
                LOGISTICS / REVERSE LOGISTICS
            </p>

            <h1>Returns</h1>

            <p>
                Approved physical returns move from buyer to this hub,
                then to the seller. Refund approval and wallet payment
                are handled outside Logistics.
            </p>
        </div>
    </header>

    @if(session('success'))
        <div class="lr-box" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="lr-box" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="lr-grid">
        <div class="lr-stat">
            <span>Approved Queue</span>
            <strong>{{ $summary->approved ?? 0 }}</strong>
            <small>Awaiting schedule</small>
        </div>

        <div class="lr-stat">
            <span>Scheduled</span>
            <strong>{{ $summary->scheduled ?? 0 }}</strong>
            <small>Waiting for pickup</small>
        </div>

        <div class="lr-stat">
            <span>At Hub</span>
            <strong>{{ $summary->hub_received ?? 0 }}</strong>
            <small>Awaiting seller handoff</small>
        </div>

        <div class="lr-stat">
            <span>Completed</span>
            <strong>{{ $summary->completed ?? 0 }}</strong>
            <small>Handed to seller</small>
        </div>
    </div>

    <section class="rm-panel">
        <div class="rm-panel-header">
            <div>
                <h2>Return Operations Report</h2>

                <p>
                    Exports physical-return activity only.
                    It does not report or transfer refund money.
                </p>
            </div>

            <form
                method="GET"
                action="{{ route('logistics.returns.report') }}"
                class="lr-form"
            >
                <input
                    type="date"
                    name="from"
                    value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                    required
                >

                <input
                    type="date"
                    name="to"
                    value="{{ now()->format('Y-m-d') }}"
                    required
                >

                <button class="rm-button rm-button-outline">
                    Download PDF Report
                </button>
            </form>
        </div>
    </section>

    <section class="rm-panel" style="margin-top: 20px">
        <div class="rm-panel-header">
            <div>
                <h2>
                    Approved Returns and Reverse-Logistics History
                </h2>

                <p>
                    Schedule only with a Seller Pickup rider whose
                    registered area matches the buyer pickup address.
                </p>
            </div>

            <form method="GET">
                <select
                    name="status"
                    onchange="this.form.submit()"
                >
                    <option value="all">All statuses</option>

                    @foreach([
                        'approved',
                        'scheduled',
                        'picked_up',
                        'received_at_hub',
                        'handed_to_seller',
                        'pickup_failed',
                    ] as $value)
                        <option
                            value="{{ $value }}"
                            @selected($status === $value)
                        >
                            {{ ucwords(str_replace('_', ' ', $value)) }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="rm-table-scroll">
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Return</th>
                        <th>Buyer Pickup</th>
                        <th>Rider / Schedule</th>
                        <th>Status</th>
                        <th>Logistics Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($cases as $case)
                        <tr>
                            <td>
                                <strong>
                                    {{ $case->return_reference }}
                                </strong>

                                <br>

                                <span class="lr-small">
                                    {{ $case->return_reason }}
                                </span>

                                <br>

                                <span class="lr-small">
                                    Seller destination:
                                    {{ $case->seller_return_destination }}
                                </span>
                            </td>

                            <td>
                                {{ $case->buyer_name }}

                                <br>

                                {{ $case->buyer_phone }}

                                <br>

                                <span class="lr-small">
                                    {{ \App\Support\RegisteredRiderArea::label(
                                        $case->pickup_address
                                    ) }}
                                </span>
                            </td>

                            <td>
                                {{ $case->rider_name ?? 'Not assigned' }}

                                <br>

                                @if($case->scheduled_date)
                                    <span class="lr-small">
                                        {{ $case->scheduled_date }}

                                        <br>

                                        {{ $case->schedule_start }}
                                        –
                                        {{ $case->schedule_end }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                {{ ucwords(
                                    str_replace('_', ' ', $case->status)
                                ) }}

                                <br>

                                <span class="lr-small">
                                    Approved:
                                    {{ $case->approved_at }}
                                </span>
                            </td>

                            <td>
                                @if(in_array(
                                    $case->status,
                                    [
                                        'approved',
                                        'pickup_failed',
                                        'scheduled',
                                    ],
                                    true
                                ))
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'logistics.returns.schedule',
                                            $case->id
                                        ) }}"
                                        class="lr-form"
                                    >
                                        @csrf

                                        <select
                                            name="rider_id"
                                            required
                                        >
                                            <option value="">
                                                Select area-matched pickup rider
                                            </option>

                                            @foreach($riders as $rider)
                                                @if(
                                                    \App\Support\RegisteredRiderArea::matches(
                                                        $rider,
                                                        $case->pickup_address
                                                    )
                                                )
                                                    <option
                                                        value="{{ $rider->id }}"
                                                        @selected(
                                                            $case->rider_id
                                                            === $rider->id
                                                        )
                                                    >
                                                        {{ $rider->name }}
                                                        —
                                                        {{ \App\Support\RegisteredRiderArea::label($rider) }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>

                                        <input
                                            type="date"
                                            name="scheduled_date"
                                            value="{{ $case->scheduled_date }}"
                                            required
                                        >

                                        <input
                                            type="time"
                                            name="schedule_start"
                                            value="{{ $case->schedule_start }}"
                                            required
                                        >

                                        <input
                                            type="time"
                                            name="schedule_end"
                                            value="{{ $case->schedule_end }}"
                                            required
                                        >

                                        <textarea
                                            name="note"
                                            rows="2"
                                            placeholder="Instructions for rider / buyer"
                                        ></textarea>

                                        <button
                                            class="rm-button rm-button-primary"
                                        >
                                            Save Pickup Schedule
                                        </button>
                                    </form>
                                @elseif($case->status === 'picked_up')
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'logistics.returns.receive',
                                            $case->id
                                        ) }}"
                                        class="lr-form"
                                    >
                                        @csrf

                                        <input
                                            name="return_reference"
                                            required
                                            placeholder="Scan or enter return reference"
                                        >

                                        <input
                                            name="condition"
                                            maxlength="120"
                                            placeholder="Condition at hub"
                                        >

                                        <textarea
                                            name="remarks"
                                            rows="2"
                                            placeholder="Hub receiving remarks"
                                        ></textarea>

                                        <button
                                            class="rm-button rm-button-primary"
                                        >
                                            Receive at Hub
                                        </button>
                                    </form>
                                @elseif($case->status === 'received_at_hub')
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'logistics.returns.hand-to-seller',
                                            $case->id
                                        ) }}"
                                        class="lr-form"
                                    >
                                        @csrf

                                        <input
                                            name="recipient"
                                            required
                                            maxlength="150"
                                            placeholder="Seller recipient name"
                                        >

                                        <textarea
                                            name="remarks"
                                            rows="2"
                                            placeholder="Handoff remarks"
                                        ></textarea>

                                        <button
                                            class="rm-button rm-button-primary"
                                        >
                                            Confirm Seller Handoff
                                        </button>
                                    </form>
                                @elseif($case->status === 'handed_to_seller')
                                    <span class="lr-small">
                                        Seller received:
                                        {{ $case->seller_handed_at }}

                                        <br>

                                        {{ $case->seller_recipient_name }}
                                    </span>
                                @else
                                    <span class="lr-small">
                                        Waiting for rider action in Rider app.
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No approved physical returns were sent by Admin yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lr-pages">
            <a href="{{ $cases->previousPageUrl() ?? '#' }}">
                Previous
            </a>

            <span>
                Page {{ $cases->currentPage() }}
                of {{ $cases->lastPage() }}
            </span>

            <a href="{{ $cases->nextPageUrl() ?? '#' }}">
                Next
            </a>
        </div>
    </section>

    <p class="rm-note">
        The rider app must call
        <code>markPickedUp()</code>
        after scanning the return reference.
        Seller handoff is the factual status the Admin system can use
        for its separate refund policy.
    </p>
</div>
@endsection