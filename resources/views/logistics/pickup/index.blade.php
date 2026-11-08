@extends('layouts.logistics')

@section('title', 'Pickup Requests — Lumear Logistics')

@section('content')

<style>
    .pickup-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .pickup-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .pickup-header h1 {
        margin: 6px 0 8px;
        font-size: 28px;
        font-weight: 800;
    }

    .pickup-header p {
        margin: 0;
        opacity: .7;
        font-size: 13px;
    }

    .pickup-summary {
        display: grid;
        grid-template-columns: repeat(
            3,
            minmax(0, 1fr)
        );
        gap: 16px;
    }

    .pickup-summary-card {
        padding: 22px;
        border-radius: 14px;
        background: var(--card-bg, rgba(128,128,128,.08));
        border: 1px solid rgba(128,128,128,.16);
    }

    .pickup-summary-card span {
        display: block;
        font-size: 12px;
        opacity: .7;
        margin-bottom: 12px;
    }

    .pickup-summary-card strong {
        display: block;
        font-size: 30px;
        font-weight: 800;
    }

    .pickup-panel {
        border-radius: 14px;
        background: var(--card-bg, rgba(128,128,128,.08));
        border: 1px solid rgba(128,128,128,.16);
        overflow: hidden;
    }

    .pickup-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding: 20px;
        border-bottom: 1px solid rgba(128,128,128,.16);
    }

    .pickup-toolbar input,
    .pickup-toolbar select {
        min-height: 42px;
        border: 1px solid rgba(128,128,128,.3);
        border-radius: 8px;
        padding: 10px 12px;
        background: transparent;
        color: inherit;
        font: inherit;
        font-size: 13px;
    }

    .pickup-toolbar input {
        flex: 1;
        min-width: 190px;
    }

    .pickup-toolbar select {
        min-width: 160px;
    }

    .pickup-toolbar select option {
        color: #222;
        background: #fff;
    }

    .pickup-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        background: #873632;
        color: #fff;
        font: inherit;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .pickup-button:hover {
        background: #62191c;
    }

    .pickup-table-wrap {
        overflow-x: auto;
    }

    .pickup-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
    }

    .pickup-table th,
    .pickup-table td {
        padding: 17px 18px;
        border-bottom: 1px solid rgba(128,128,128,.12);
        vertical-align: middle;
    }

    .pickup-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .06em;
        opacity: .7;
        white-space: nowrap;
    }

    .pickup-table td {
        overflow-wrap: anywhere;
    }

    .pickup-table td small {
        display: block;
        margin-top: 5px;
        opacity: .6;
    }

    .pickup-status {
        display: inline-block;
        padding: 7px 11px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(202,174,159,.18);
        color: #caae9f;
        white-space: nowrap;
    }

    .pickup-empty {
        padding: 65px 20px;
        text-align: center;
    }

    .pickup-empty strong {
        display: block;
        margin-bottom: 10px;
        font-size: 16px;
    }

    .pickup-empty p {
        opacity: .7;
        font-size: 13px;
    }

    .pickup-pagination {
        padding: 20px;
    }

    @media (max-width: 700px) {
        .pickup-summary {
            grid-template-columns: 1fr;
        }

        .pickup-header h1 {
            font-size: 23px;
        }

        .pickup-toolbar input,
        .pickup-toolbar select,
        .pickup-toolbar button {
            width: 100%;
        }
    }
</style>

<div class="pickup-page">

    <div class="pickup-header">

        <div>
            <span class="page-eyebrow">
                LOGISTICS OPERATIONS
            </span>

            <h1>Pickup Requests</h1>

            <p>
                Review seller pickup requests assigned to
                {{ $provider->name }}.
            </p>
        </div>

        <a
            href="{{ route('logistics.dashboard') }}"
            class="pickup-button"
        >
            Back to Dashboard
        </a>

    </div>

    <div class="pickup-summary">

        <div class="pickup-summary-card">
            <span>Total Assigned Requests</span>
            <strong>
                {{ number_format($counts->total ?? 0) }}
            </strong>
        </div>

        <div class="pickup-summary-card">
            <span>Ready for Pickup</span>
            <strong>
                {{ number_format($counts->pending ?? 0) }}
            </strong>
        </div>

        <div class="pickup-summary-card">
            <span>Shipped Seller Orders</span>
            <strong>
                {{ number_format($counts->shipped ?? 0) }}
            </strong>
        </div>

    </div>

    <div class="pickup-panel">

        <form
            action="{{ route('logistics.pickup.index') }}"
            method="GET"
            class="pickup-toolbar"
        >

            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search order, seller or tracking code"
                aria-label="Search pickup requests"
            >

            <select name="status" aria-label="Filter by status">
                <option value="ready_to_ship"
                    @selected($status === 'ready_to_ship')
                >
                    Ready to Ship
                </option>

                <option value="all"
                    @selected($status === 'all')
                >
                    All Statuses
                </option>

                <option value="shipped"
                    @selected($status === 'shipped')
                >
                    Shipped
                </option>

                <option value="delivered"
                    @selected($status === 'delivered')
                >
                    Delivered
                </option>

                <option value="cancelled"
                    @selected($status === 'cancelled')
                >
                    Cancelled
                </option>
            </select>

            <button type="submit" class="pickup-button">
                Apply Filters
            </button>

        </form>

        <div class="pickup-table-wrap">

            <table class="pickup-table">

                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Created</th>
                        <th>Seller Status</th>
                        <th>Shipment</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($pickups as $pickup)

                        <tr>

                            <td>
                                <strong>
                                    {{ $pickup->reference }}
                                </strong>

                                <small>
                                    Seller Order #{{ $pickup->id }}
                                </small>
                            </td>

                            <td>
                                {{ $pickup->seller_name ?? 'Unknown Seller' }}
                            </td>

                            <td>
                                {{ $pickup->created_at
                                    ? \Carbon\Carbon::parse(
                                        $pickup->created_at
                                    )->format('M d, Y h:i A')
                                    : 'Not available' }}
                            </td>

                            <td>
                                <span class="pickup-status">
                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $pickup->status
                                        )
                                    ) }}
                                </span>
                            </td>

                            <td>
                                @if($pickup->shipment_id)
                                    <strong>
                                        {{ $pickup->tracking_code }}
                                    </strong>

                                    <small>
                                        {{ ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $pickup->shipment_status
                                            )
                                        ) }}
                                    </small>
                                @else
                                    <span style="opacity:.6;">
                                        Not created
                                    </span>
                                @endif
                            </td>

                            <td>
                                <a
                                    href="{{ route(
                                        'logistics.pickup.show',
                                        $pickup->id
                                    ) }}"
                                    class="pickup-button"
                                >
                                    Review
                                </a>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="6">

                                <div class="pickup-empty">

                                    <strong>
                                        No pickup requests found
                                    </strong>

                                    <p>
                                        Seller orders assigned to this
                                        Logistics provider will appear
                                        here when their status matches
                                        your selected filter.
                                    </p>

                                </div>

                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if($pickups->hasPages())
            <div class="pickup-pagination">
                {{ $pickups->links() }}
            </div>
        @endif

    </div>

</div>

@endsection
