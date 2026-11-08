@extends('layouts.logistics')

@section('title', 'Semi-Automatic Assignment | LUMEAR Logistics')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/logistics/assignment.css') }}"
>

<style>
    .sa-message,
    .sa-footer {
        padding: 16px;
    }

    .sa-footer {
        display: flex;
        gap: 16px;
        align-items: center;
        flex-wrap: wrap;
    }

    .sa-select {
        width: 100%;
        min-width: 250px;
        padding: 10px;
        border: 1px solid #998484;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
    }

    .sa-select option {
        background: white;
        color: #222;
    }

    .sa-panel {
        margin-top: 20px;
    }

    .sa-description {
        margin-top: 6px;
        line-height: 1.6;
    }
</style>
@endpush

@section('content')
<div class="da-page">
    <header class="da-heading">
        <div>
            <p class="da-eyebrow">
                DELIVERY OPERATIONS
            </p>

            <h1>Semi-Automatic Assignment</h1>

            <p class="da-description">
                Select parcels, review suggested riders,
                and confirm the assignments.
            </p>
        </div>

        <div class="da-heading-actions">
            <a
                class="da-btn da-btn-outline"
                href="{{ route('logistics.assignment.index') }}"
            >
                Manual Assignment / Dispatch
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="sa-message" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="sa-message" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($plan !== null)
        <section class="da-panel sa-panel">
            <div class="da-panel-heading">
                <div>
                    <p class="da-eyebrow">
                        REVIEW BEFORE SAVING
                    </p>

                    <h2>Suggested Assignments</h2>

                    <p class="sa-description">
                        Only riders whose registered municipality
                        and province match the parcel destination
                        are listed. Change the rider or choose
                        Leave unassigned.
                    </p>
                </div>
            </div>

            @if(count($plan['rows']))
                <form
                    method="POST"
                    action="{{ route('logistics.semi-assignment.confirm') }}"
                >
                    @csrf

                    <div class="da-table-scroll">
                        <table class="da-table">
                            <thead>
                                <tr>
                                    <th>Waybill</th>
                                    <th>Suggested / Selected Rider</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($plan['rows'] as $index => $row)
                                    <tr>
                                        <td>
                                            {{ $row['tracking'] }}

                                            <input
                                                type="hidden"
                                                name="assignments[{{ $index }}][shipment_id]"
                                                value="{{ $row['id'] }}"
                                            >
                                        </td>

                                        <td>
                                            <select
                                                class="sa-select"
                                                name="assignments[{{ $index }}][rider_id]"
                                                aria-label="Rider for {{ $row['tracking'] }}"
                                            >
                                                <option value="">
                                                    Leave unassigned
                                                </option>

                                                @foreach($plan['riders'] as $rider)
                                                    @continue(
                                                        !in_array(
                                                            $rider['id'],
                                                            $row['eligible_rider_ids'],
                                                            true
                                                        )
                                                    )

                                                    <option
                                                        value="{{ $rider['id'] }}"
                                                        @selected(
                                                            $row['rider_id'] === $rider['id']
                                                        )
                                                        @disabled(
                                                            $rider['load'] >= $rider['capacity']
                                                        )
                                                    >
                                                        {{ $rider['name'] }}
                                                        — {{ $rider['area'] }}
                                                        —
                                                        {{ $rider['load'] }}/{{ $rider['capacity'] }}
                                                        currently active
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>

                                        <td>
                                            {{ $row['reason'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="sa-footer">
                        <button
                            type="submit"
                            class="da-btn da-btn-primary"
                        >
                            Confirm Selected Assignments
                        </button>

                        <a
                            class="da-btn da-btn-outline"
                            href="{{ route('logistics.semi-assignment.index') }}"
                        >
                            Discard Preview
                        </a>

                        <span>
                            Availability, registered area, and capacity
                            are checked again when saving.
                        </span>
                    </div>
                </form>
            @else
                <div class="sa-message">
                    No selected parcels can be suggested
                    for local delivery.
                </div>
            @endif

            @if(count($plan['skipped']))
                <div class="sa-message">
                    <strong>Excluded Parcels</strong>

                    <ul>
                        @foreach($plan['skipped'] as $item)
                            <li>
                                <strong>{{ $item['tracking'] }}</strong>:
                                {{ $item['reason'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <section class="da-panel sa-panel">
        <div class="da-panel-heading">
            <div>
                <p class="da-eyebrow">
                    PREPARE A NEW PREVIEW
                </p>

                <h2>Select Parcels</h2>

                <p class="sa-description">
                    Select verified and sorted parcels awaiting
                    assignment. Oldest sorted parcels appear first.
                </p>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('logistics.semi-assignment.preview') }}"
        >
            @csrf

            <div class="da-table-scroll">
                <table class="da-table">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Waybill</th>
                            <th>Destination</th>
                            <th>Sorted At</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($queue as $parcel)
                            @php
                                $address = json_decode(
                                    $parcel->shipping_address ?: '{}',
                                    true
                                );

                                $address = is_array($address)
                                    ? $address
                                    : [];

                                $city = $address['city']
                                    ?? $address['municipality']
                                    ?? '';

                                $province = $address['province'] ?? '';

                                $barangay = $address['barangay'] ?? '';

                                $destination = implode(', ', array_filter([
                                    is_string($barangay) ? $barangay : '',
                                    is_string($city) ? $city : '',
                                    is_string($province) ? $province : '',
                                ]));
                            @endphp

                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="shipment_ids[]"
                                        value="{{ $parcel->id }}"
                                        aria-label="Select {{ $parcel->tracking_code }}"
                                    >
                                </td>

                                <td>
                                    {{ $parcel->tracking_code }}
                                </td>

                                <td>
                                    {{ $destination ?: 'Destination missing' }}
                                </td>

                                <td>
                                    {{ $parcel->sorted_at }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    No eligible parcels awaiting assignment.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="sa-footer">
                <button
                    type="submit"
                    class="da-btn da-btn-primary"
                    @disabled($queue->isEmpty())
                >
                    Generate Suggestions
                </button>

                <span>
                    Generating suggestions does not save
                    parcel assignments.
                </span>
            </div>
        </form>

        <div class="sa-footer">
            @if($queue->previousPageUrl())
                <a href="{{ $queue->previousPageUrl() }}">
                    Previous Parcels
                </a>
            @endif

            <span>
                Page {{ $queue->currentPage() }}
                of {{ $queue->lastPage() }}
            </span>

            @if($queue->nextPageUrl())
                <a href="{{ $queue->nextPageUrl() }}">
                    Next Parcels
                </a>
            @endif
        </div>
    </section>

    <div class="sa-message">
        <strong>Assignment Rules</strong>

        <ul>
            <li>
                The rider must be registered for Delivery.
            </li>
            <li>
                The parcel must match the rider’s registered
                municipality and province.
            </li>
            <li>
                Any barangay within that registered municipality
                is eligible.
            </li>
            <li>
                The rider must be available and pass COD
                restriction checks.
            </li>
            <li>
                A rider can have at most five active delivery parcels.
            </li>
            <li>
                Logistics reviews and confirms assignments.
                Dispatch remains separate.
            </li>
        </ul>
    </div>
</div>
@endsection