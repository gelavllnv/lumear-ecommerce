@extends('layouts.logistics')

@section('title', 'Hub Transfers | LUMEAR Logistics')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/logistics/riders.css') }}"
>

<style>
    .ht-message,
    .ht-form {
        padding: 16px;
    }

    .ht-form {
        display: grid;
        gap: 10px;
        min-width: 230px;
    }

    .ht-form input,
    .ht-form textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #998484;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
    }

    .ht-pages {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 16px;
    }
</style>
@endpush

@section('content')
<div class="rm-page">
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">
                LOGISTICS / HUB TRANSFERS
            </p>

            <h1>Hub Transfers</h1>

            <p>
                Dispatch from the origin hub.
                Confirm physical receipt at the destination hub.
            </p>
        </div>

        <div class="rm-header-actions">
            <a
                class="rm-button rm-button-outline"
                href="{{ route('logistics.tasks.index', [
                    'job_type' => \App\Support\RiderJobType::HUB_TRANSFER,
                ]) }}"
            >
                Assign a Transfer
            </a>

            <a
                class="rm-button rm-button-outline"
                href="{{ route('logistics.incoming.index') }}"
            >
                Incoming Parcels
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="ht-message" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="ht-message" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rm-panel">
        <div class="rm-panel-header">
            <div>
                <h2>Awaiting Dispatch</h2>

                <p>
                    Origin hub: dispatch only after handing
                    the parcel to the assigned driver.
                </p>
            </div>
        </div>

        <div class="rm-table-scroll">
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Waybill</th>
                        <th>Driver</th>
                        <th>Destination</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($pending as $task)
                        <tr>
                            <td>{{ $task->id }}</td>
                            <td>{{ $task->tracking_code }}</td>
                            <td>{{ $task->driver }}</td>
                            <td>{{ $task->destination }}</td>

                            <td>
                                <form
                                    method="POST"
                                    action="{{ route(
                                        'logistics.transfers.dispatch',
                                        $task->id
                                    ) }}"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="rm-button rm-button-primary"
                                    >
                                        Dispatch Transfer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No transfer tasks awaiting dispatch.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ht-pages">
            @if($pending->previousPageUrl())
                <a href="{{ $pending->previousPageUrl() }}">
                    Previous
                </a>
            @else
                <span></span>
            @endif

            <span>
                Page {{ $pending->currentPage() }}
                of {{ $pending->lastPage() }}
            </span>

            @if($pending->nextPageUrl())
                <a href="{{ $pending->nextPageUrl() }}">
                    Next
                </a>
            @else
                <span></span>
            @endif
        </div>
    </section>

    <section class="rm-panel" style="margin-top: 20px">
        <div class="rm-panel-header">
            <div>
                <h2>Incoming Transfers and History</h2>

                <p>
                    Only the designated destination hub
                    can confirm receipt.
                </p>
            </div>
        </div>

        <div class="rm-table-scroll">
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Transfer / Waybill</th>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Status</th>
                        <th>Receipt</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($transfers as $transfer)
                        <tr>
                            <td>
                                #{{ $transfer->id }}
                                <br>
                                {{ $transfer->tracking_code }}
                            </td>

                            <td>{{ $transfer->source_name }}</td>
                            <td>{{ $transfer->destination_name }}</td>

                            <td>
                                {{ $transfer->status === 'received'
                                    ? 'Received'
                                    : 'In Transit' }}

                                <br>

                                <small>
                                    Dispatched:
                                    {{ $transfer->dispatched_at }}
                                </small>
                            </td>

                            <td>
                                @if(
                                    $transfer->status === 'in_transit'
                                    && (int) $transfer->destination_provider_id
                                        === $providerId
                                )
                                    <form
                                        class="ht-form"
                                        method="POST"
                                        action="{{ route(
                                            'logistics.transfers.receive',
                                            $transfer->id
                                        ) }}"
                                    >
                                        @csrf

                                        <label>
                                            Waybill on the physical parcel

                                            <input
                                                name="tracking_code"
                                                required
                                                maxlength="255"
                                                autocomplete="off"
                                                placeholder="Scan or enter waybill"
                                            >
                                        </label>

                                        <label>
                                            Parcel condition

                                            <input
                                                name="condition"
                                                maxlength="120"
                                                placeholder="Example: Intact"
                                            >
                                        </label>

                                        <label>
                                            Receipt remarks

                                            <textarea
                                                name="remarks"
                                                rows="2"
                                                maxlength="500"
                                            ></textarea>
                                        </label>

                                        <button
                                            class="rm-button rm-button-primary"
                                            type="submit"
                                        >
                                            Confirm Physical Receipt
                                        </button>
                                    </form>
                                @elseif($transfer->status === 'received')
                                    {{ $transfer->received_at }}
                                    <br>
                                    {{ $transfer->condition ?? '' }}
                                    <br>
                                    {{ $transfer->receipt_remarks ?? '' }}
                                @else
                                    Awaiting confirmation from
                                    destination hub.
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No dispatched hub transfers yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ht-pages">
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

    <p class="rm-note">
        Receiving completes the hub-transfer task, not customer delivery.
        The destination hub must verify the waybill again and complete
        sorting before assigning customer delivery.
    </p>
</div>
@endsection