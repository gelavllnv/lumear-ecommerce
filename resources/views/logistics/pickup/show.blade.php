@extends('layouts.logistics')

@section('title', 'Pickup Details — Lumear Logistics')

@section('content')

@php
    $shippingAddress = json_decode(
        $pickup->shipping_address ?? '{}',
        true
    );

    if (!is_array($shippingAddress)) {
        $shippingAddress = [];
    }

    $formattedAddress = collect([
        $shippingAddress['line1'] ?? null,
        $shippingAddress['barangay'] ?? null,
        $shippingAddress['city'] ?? null,
        $shippingAddress['province'] ?? null,
        $shippingAddress['postal_code'] ?? null,
    ])->filter()->implode(', ');
@endphp

<style>
    .pickup-details {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .pickup-details h1 {
        margin: 8px 0;
        font-size: 28px;
        font-weight: 800;
    }

    .pickup-details h2 {
        margin: 0 0 20px;
        font-size: 17px;
    }

    .pickup-details-card {
        border: 1px solid rgba(128,128,128,.18);
        border-radius: 14px;
        padding: 24px;
        background: var(--card-bg, rgba(128,128,128,.08));
    }

    .pickup-details-grid {
        display: grid;
        grid-template-columns: repeat(
            2,
            minmax(0, 1fr)
        );
        gap: 22px;
    }

    .pickup-details-field span {
        display: block;
        font-size: 12px;
        opacity: .65;
        margin-bottom: 7px;
    }

    .pickup-details-field strong {
        display: block;
        font-size: 13px;
        overflow-wrap: anywhere;
    }

    .pickup-details-table-wrap {
        overflow-x: auto;
    }

    .pickup-details-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13px;
    }

    .pickup-details-table th,
    .pickup-details-table td {
        padding: 14px;
        border-bottom: 1px solid rgba(128,128,128,.15);
    }

    .pickup-details-button {
        display: inline-block;
        padding: 12px 18px;
        background: #873632;
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
    }

    .pickup-details-notice {
        border-left: 3px solid #caae9f;
        padding: 15px 18px;
        background: rgba(202,174,159,.09);
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.7;
    }

    @media (max-width: 700px) {
        .pickup-details-grid {
            grid-template-columns: 1fr;
        }

        .pickup-details h1 {
            font-size: 23px;
        }
    }
</style>

<div class="pickup-details">

    <div>
        <span class="page-eyebrow">
            PICKUP MANAGEMENT
        </span>

        <h1>
            Pickup Request Details
        </h1>

        <p>
            Order {{ $pickup->reference }}
        </p>
    </div>

    <div>
        <a
            href="{{ route('logistics.pickup.index') }}"
            class="pickup-details-button"
        >
            Back to Pickup Requests
        </a>
    </div>

    <section class="pickup-details-card">

        <h2>Order Information</h2>

        <div class="pickup-details-grid">

            <div class="pickup-details-field">
                <span>Order Reference</span>
                <strong>{{ $pickup->reference }}</strong>
            </div>

            <div class="pickup-details-field">
                <span>Seller Order ID</span>
                <strong>#{{ $pickup->id }}</strong>
            </div>

            <div class="pickup-details-field">
                <span>Seller</span>
                <strong>
                    {{ $pickup->seller_name ?? 'Unknown Seller' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Seller Email</span>
                <strong>
                    {{ $pickup->seller_email ?? 'Not available' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Seller Order Status</span>
                <strong>
                    {{ ucwords(str_replace('_', ' ', $pickup->status)) }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Payment Method</span>
                <strong>
                    {{ strtoupper($pickup->payment_method) }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Payment Status</span>
                <strong>
                    {{ ucfirst($pickup->payment_status) }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Shipping Fee</span>
                <strong>
                    ₱{{ number_format(
                        $pickup->shipping_fee_minor / 100,
                        2
                    ) }}
                </strong>
            </div>

        </div>

    </section>

    <section class="pickup-details-card">

        <h2>Delivery Address</h2>

        <div class="pickup-details-grid">

            <div class="pickup-details-field">
                <span>Recipient</span>
                <strong>
                    {{ $shippingAddress['recipient']
                        ?? 'Not available' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Contact Number</span>
                <strong>
                    {{ $shippingAddress['phone']
                        ?? 'Not available' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Shipping Address</span>
                <strong>
                    {{ $formattedAddress ?: 'Not available' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Assigned Logistics</span>
                <strong>{{ $provider->name }}</strong>
            </div>

        </div>

    </section>

    <section class="pickup-details-card">

        <h2>Ordered Products</h2>

        <div class="pickup-details-table-wrap">

            <table class="pickup-details-table">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Variant</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($items as $item)

                        <tr>
                            <td>
                                {{ $item->product_name }}
                            </td>

                            <td>
                                {{ $item->variant_name }}
                            </td>

                            <td>
                                {{ $item->quantity }}
                            </td>

                            <td>
                                ₱{{ number_format(
                                    $item->unit_price_minor / 100,
                                    2
                                ) }}
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="4">
                                No order items found.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <section class="pickup-details-card">

        <h2>Shipment Information</h2>

        <div class="pickup-details-grid">

            <div class="pickup-details-field">
                <span>Shipment ID</span>
                <strong>
                    {{ $pickup->shipment_id
                        ? '#' . $pickup->shipment_id
                        : 'Not created' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Tracking Code</span>
                <strong>
                    {{ $pickup->tracking_code
                        ?? 'Not assigned' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Shipment Status</span>
                <strong>
                    {{ $pickup->shipment_status
                        ? ucwords(str_replace(
                            '_',
                            ' ',
                            $pickup->shipment_status
                        ))
                        : 'Awaiting shipment creation' }}
                </strong>
            </div>

            <div class="pickup-details-field">
                <span>Assigned Rider ID</span>
                <strong>
                    {{ $pickup->rider_id
                        ? '#' . $pickup->rider_id
                        : 'Not assigned' }}
                </strong>
            </div>

        </div>

    </section>

    <div class="pickup-details-notice">

        <strong>Pickup processing</strong>

        <p>
            This page currently supports reviewing real
            pickup requests. Shipment creation, rider
            assignment, and pickup confirmation will be
            enabled after their validation and payment
            rules are connected.
        </p>

    </div>

</div>

@endsection
