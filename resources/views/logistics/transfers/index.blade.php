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
        min-width: 250px;
    }

    .ht-form input:not([type="checkbox"]),
    .ht-form textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #998484;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
    }

    .ht-check {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        white-space: normal;
    }

    .ht-check input {
        margin-top: 4px;
    }

    .ht-pages {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 16px;
    }

    .ht-panel {
        margin-top: 20px;
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
                <h2>Origin Hub: Awaiting Dispatch</h2>

                <p>
                    Register departure after handing the parcel
                    to its assigned truck driver.
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
                        <th>Truck Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($pending as $task)
                        <tr>
                            <td>#{{ $task->id }}</td>
                            <td>{{ $task->tracking_code }}</td>
                            <td>{{ $task->driver }}</td>
                            <td>{{ $task->destination }}</td>
                            <td>{{ $task->truck_status }}</td>

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
                            <td colspan="6">
                                No transfer tasks awaiting dispatch.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ht-pages">
            <a href="{{ $pending->previousPageUrl() ?? '#' }}">
                Previous
            </a>

            <span>
                Page {{ $pending->currentPage() }}
                of {{ $pending->lastPage() }}
            </span>

            <a href="{{ $pending->nextPageUrl() ?? '#' }}">
                Next
            </a>
        </div>
    </section>

    <section class="rm-panel ht-panel">
        <div class="rm-panel-header">
            <div>
                <h2>
                    Destination Hub:
                    Incoming Tasks Without Dispatch Registration
                </h2>

                <p>
                    These tasks are assigned to this hub.
                    Confirm only when the physical parcel is here.
                </p>

                <p>
                    A truck status such as Arrived does not confirm
                    hub receipt. Missing origin departure details
                    remain unconfirmed.
                </p>
            </div>
        </div>

        <div class="rm-table-scroll">
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Task / Waybill</th>
                        <th>Origin</th>
                        <th>Driver</th>
                        <th>Truck Status</th>
                        <th>Physical Receipt</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($incomingTasks as $task)
                        <tr>
                            <td>
                                #{{ $task->id }}
                                <br>
                                {{ $task->tracking_code }}
                            </td>

                            <td>{{ $task->origin }}</td>
                            <td>{{ $task->driver }}</td>
                            <td>{{ $task->truck_status }}</td>

                            <td>
                                @include(
                                    'logistics.transfers.receipt-form',
                                    [
                                        'receiptAction' => route(
                                            'logistics.transfers.receive-task',
                                            $task->id
                                        ),
                                    ]
                                )
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No incoming tasks without dispatch registration.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ht-pages">
            <a href="{{ $incomingTasks->previousPageUrl() ?? '#' }}">
                Previous
            </a>

            <span>
                Page {{ $incomingTasks->currentPage() }}
                of {{ $incomingTasks->lastPage() }}
            </span>

            <a href="{{ $incomingTasks->nextPageUrl() ?? '#' }}">
                Next
            </a>
        </div>
    </section>

    <section class="rm-panel ht-panel">
        <div class="rm-panel-header">
            <div>
                <h2>Registered Transfers and History</h2>

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

                                @if($transfer->origin_dispatch_confirmed)
                                    <small>
                                        Dispatched:
                                        {{ $transfer->dispatched_at }}
                                    </small>
                                @else
                                    <small>
                                        Origin departure not registered.
                                        <br>
                                        Handover registered at destination:
                                        {{ $transfer->registered_at }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                @if(
                                    $transfer->status === 'in_transit'
                                    && (int) $transfer->destination_provider_id
                                        === $providerId
                                )
                                    @include(
                                        'logistics.transfers.receipt-form',
                                        [
                                            'receiptAction' => route(
                                                'logistics.transfers.receive',
                                                $transfer->id
                                            ),
                                        ]
                                    )
                                @elseif($transfer->status === 'received')
                                    {{ $transfer->received_at }}
                                    <br>
                                    {{ $transfer->condition ?? '' }}
                                    <br>
                                    {{ $transfer->receipt_remarks ?? '' }}
                                @else
                                    Awaiting confirmation from destination hub.
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No registered hub transfers yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ht-pages">
            <a href="{{ $transfers->previousPageUrl() ?? '#' }}">
                Previous
            </a>

            <span>
                Page {{ $transfers->currentPage() }}
                of {{ $transfers->lastPage() }}
            </span>

            <a href="{{ $transfers->nextPageUrl() ?? '#' }}">
                Next
            </a>
        </div>
    </section>

    <p class="rm-note">
        Receiving completes the hub-transfer task.
        It does not mark customer delivery complete.
        Verify the waybill and sort the parcel at this hub
        before assigning its next journey.
    </p>
</div>
@endsection