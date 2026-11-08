@extends('layouts.logistics')

@section('title', 'Rider Tasks | LUMEAR Logistics')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/logistics/riders.css') }}"
>

<style>
    .rt-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        padding: 20px;
    }

    .rt-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .rt-field select,
    .rt-field textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #998484;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
    }

    .rt-field option {
        background: #fff;
        color: #222;
    }

    .rt-wide {
        grid-column: 1 / -1;
    }

    .rt-message {
        padding: 16px;
        margin-bottom: 16px;
        border: 1px solid #998484;
        border-radius: 8px;
    }

    .rt-tabs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .rt-pagination {
        padding: 16px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }

    @media (max-width: 700px) {
        .rt-form {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="rm-page">
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">
                LOGISTICS / RIDER TASKS
            </p>

            <h1>Rider Tasks</h1>

            <p>
                Assign work that matches the job type selected
                in the rider app.
            </p>
        </div>

        <div class="rm-header-actions">
            <a
                class="rm-button rm-button-primary"
                href="{{ route('logistics.transfers.index') }}"
            >
                Hub Dispatch & Receiving
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="rt-message" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rt-message" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <nav class="rt-tabs" aria-label="Task types">
        @foreach(\App\Support\RiderJobType::values() as $type)
            <a
                class="rm-button {{
                    $jobType === $type
                        ? 'rm-button-primary'
                        : 'rm-button-outline'
                }}"
                href="{{ route('logistics.tasks.index', [
                    'job_type' => $type,
                ]) }}"
                @if($jobType === $type)
                    aria-current="page"
                @endif
            >
                {{ $type }}
            </a>
        @endforeach
    </nav>

    <section class="rm-panel">
        <div class="rm-panel-header">
            <div>
                <h2>{{ $jobType }}</h2>

                <p>
                    Rider roles come from registration
                    and cannot be changed here.
                </p>
            </div>
        </div>

        @if($jobType === \App\Support\RiderJobType::DELIVERY)
            <div class="rt-message">
                <p>
                    Use Delivery Assignment for customer deliveries
                    and its existing parcel-capacity checks.
                </p>

                <a
                    class="rm-button rm-button-primary"
                    href="{{ route('logistics.assignment.index') }}"
                >
                    Open Delivery Assignment
                </a>
            </div>
        @else
            <form
                class="rt-form"
                method="POST"
                action="{{ route('logistics.tasks.store') }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="job_type"
                    value="{{ $jobType }}"
                >

                <div class="rt-field">
                    <label for="rtParcel">
                        Eligible Parcel
                    </label>

                    <select
                        id="rtParcel"
                        name="shipment_id"
                        required
                    >
                        <option value="">
                            Select a parcel
                        </option>

                        @foreach($parcels as $parcel)
                            <option
                                value="{{ $parcel->id }}"
                                @selected(
                                    old('shipment_id') == $parcel->id
                                )
                            >
                                {{ $parcel->tracking_code }}
                                — {{ $parcel->status }}
                            </option>
                        @endforeach
                    </select>

                    @if($parcels->isEmpty())
                        <small>
                            No eligible parcels on this page.
                        </small>
                    @endif
                </div>

                <div class="rt-field">
                    <label for="rtRider">
                        {{ $jobType }} Rider
                    </label>

                    <select
                        id="rtRider"
                        name="rider_id"
                        required
                    >
                        <option value="">
                            Select a matching rider
                        </option>

                        @foreach($riders as $rider)
                            <option
                                value="{{ $rider->id }}"
                                @selected(
                                    old('rider_id') == $rider->id
                                )
                            >
                                {{ $rider->rider_name }}
                                — {{ $rider->vehicle_type }}
                                / {{ $rider->plate_no }}
                            </option>
                        @endforeach
                    </select>

                    <small>
                        Availability and COD restrictions
                        are checked again when saving.
                    </small>
                </div>

                @if(
                    $jobType
                        === \App\Support\RiderJobType::HUB_TRANSFER
                )
                    <div class="rt-field rt-wide">
                        <label for="rtHub">
                            Destination Hub
                        </label>

                        <select
                            id="rtHub"
                            name="destination_provider_id"
                            required
                        >
                            <option value="">
                                Select destination hub
                            </option>

                            @foreach($hubs as $hub)
                                <option
                                    value="{{ $hub->id }}"
                                    @selected(
                                        old('destination_provider_id')
                                            == $hub->id
                                    )
                                >
                                    {{ $hub->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="rt-field rt-wide">
                    <label for="rtNote">
                        Assignment Notes
                    </label>

                    <textarea
                        id="rtNote"
                        name="note"
                        rows="3"
                        maxlength="500"
                    >{{ old('note') }}</textarea>
                </div>

                <div class="rt-wide">
                    <button
                        class="rm-button rm-button-primary"
                        type="submit"
                    >
                        Assign Task
                    </button>
                </div>
            </form>

            <div class="rt-pagination">
                @if($parcels->previousPageUrl())
                    <a href="{{ $parcels->previousPageUrl() }}">
                        Previous parcels
                    </a>
                @else
                    <span></span>
                @endif

                <span>
                    Parcel page {{ $parcels->currentPage() }}
                    of {{ $parcels->lastPage() }}
                </span>

                @if($parcels->nextPageUrl())
                    <a href="{{ $parcels->nextPageUrl() }}">
                        Next parcels
                    </a>
                @else
                    <span></span>
                @endif
            </div>
        @endif
    </section>

    <section class="rm-panel" style="margin-top: 20px">
        <div class="rm-panel-header">
            <div>
                <h2>Assigned Tasks</h2>

                <p>
                    View the current status of rider assignments.
                </p>
            </div>
        </div>

        <div class="rm-table-scroll">
            <table class="rm-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Waybill</th>
                        <th>Rider</th>
                        <th>Destination Hub</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td>{{ $task->id }}</td>
                            <td>{{ $task->tracking_code }}</td>
                            <td>{{ $task->rider_name }}</td>
                            <td>
                                {{ $task->destination_name ?? '—' }}
                            </td>
                            <td>{{ $task->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No tasks for this job type yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rt-pagination">
            @if($tasks->previousPageUrl())
                <a href="{{ $tasks->previousPageUrl() }}">
                    Previous tasks
                </a>
            @else
                <span></span>
            @endif

            <span>
                Task page {{ $tasks->currentPage() }}
                of {{ $tasks->lastPage() }}
            </span>

            @if($tasks->nextPageUrl())
                <a href="{{ $tasks->nextPageUrl() }}">
                    Next tasks
                </a>
            @else
                <span></span>
            @endif
        </div>
    </section>
</div>
@endsection