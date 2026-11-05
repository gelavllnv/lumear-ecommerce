@extends('layouts.logistics')

@section('title', 'Rider Payroll | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/payroll.css') }}">
@endpush

@section('content')
@php
    $peso = fn ($minor) => '₱' . number_format($minor / 100, 2);
@endphp

<div class="rp-page">

    <div class="rp-header">
        <div>
            <span class="rp-eyebrow">RIDER MANAGEMENT / FINANCE</span>
            <h1>Rider Payroll</h1>
            <p>Manage rider salaries, delivery commissions, and payroll records.</p>
        </div>

        <div class="rp-rate-label">
            <span>Daily Base Salary</span>
            <strong>{{ $peso($dailyRate) }}</strong>
            <small>+ {{ $peso($deliveryRate) }} per delivery</small>
        </div>
    </div>

    @if(session('payroll_success'))
        <div class="rp-alert rp-success">
            {{ session('payroll_success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rp-alert rp-error">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rp-stats">
        <div class="rp-stat">
            <span>Registered Riders</span>
            <strong>{{ $riderCount }}</strong>
            <small>Under your Logistics provider</small>
        </div>

        <div class="rp-stat">
            <span>Base Salary</span>
            <strong>{{ $peso($totalBase) }}</strong>
            <small>Verified eligible paid days</small>
        </div>

        <div class="rp-stat">
            <span>Delivery Commissions</span>
            <strong>{{ $peso($totalCommission) }}</strong>
            <small>₱5 per successful delivery</small>
        </div>

        <div class="rp-stat rp-stat-featured">
            <span>Estimated Gross Payroll</span>
            <strong>{{ $peso($totalGross) }}</strong>
            <small>Before adjustments or deductions</small>
        </div>
    </div>

    <section class="rp-panel">
        <div class="rp-panel-heading">
            <div>
                <h2>Payroll Period</h2>
                <p>Select the dates you want to review.</p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('logistics.riders.payroll.index') }}"
            class="rp-filters"
        >
            <div class="rp-field">
                <label for="rpFrom">From</label>
                <input
                    type="date"
                    id="rpFrom"
                    name="from"
                    value="{{ $from }}"
                    required
                >
            </div>

            <div class="rp-field">
                <label for="rpTo">To</label>
                <input
                    type="date"
                    id="rpTo"
                    name="to"
                    value="{{ $to }}"
                    required
                >
            </div>

            <button type="submit" class="rp-button rp-primary">
                Apply Period
            </button>
        </form>
    </section>

    <section class="rp-panel">
        <div class="rp-panel-heading">
            <div>
                <h2>Rider Salary Overview</h2>
                <p>
                    Salary calculations for
                    {{ \Carbon\Carbon::parse($from)->format('M d, Y') }}
                    –
                    {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
                </p>
            </div>
        </div>

        <div class="rp-table-scroll">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Paid Days</th>
                        <th>Deliveries</th>
                        <th>Base Salary</th>
                        <th>Commission</th>
                        <th>Gross Pay</th>
                        <th>Payroll</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                <div class="rp-rider">
                                    <strong>{{ $row['name'] }}</strong>
                                    <span>
                                        RDR-{{ str_pad($row['id'], 3, '0', STR_PAD_LEFT) }}
                                        · {{ $row['active'] ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </td>

                            <td>{{ $row['paid_days'] }}</td>
                            <td>{{ $row['deliveries'] }}</td>
                            <td>{{ $peso($row['base_minor']) }}</td>
                            <td>{{ $peso($row['commission_minor']) }}</td>

                            <td class="rp-money">
                                {{ $peso($row['gross_minor']) }}
                            </td>

                            <td>
                                @if($row['saved'])
                                    <span class="rp-status rp-status-{{ $row['saved']->status }}">
                                        {{ ucfirst($row['saved']->status) }}
                                    </span>
                                @else
                                    <span class="rp-status rp-status-none">
                                        Not Generated
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if(!$row['saved'])
                                    <form
                                        method="POST"
                                        action="{{ route('logistics.riders.payroll.store') }}"
                                        class="rp-generate-form"
                                    >
                                        @csrf

                                        <input type="hidden" name="rider_id" value="{{ $row['id'] }}">
                                        <input type="hidden" name="from" value="{{ $from }}">
                                        <input type="hidden" name="to" value="{{ $to }}">

                                        <button
                                            type="submit"
                                            class="rp-button rp-small"
                                        >
                                            Generate Draft
                                        </button>
                                    </form>
                                @else
                                    <span class="rp-saved-label">
                                        Record saved
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="rp-empty">
                                No riders found for this Logistics provider.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="rp-note">
        <strong>Payroll policy:</strong>
        Riders receive ₱630 for each verified eligible paid working day,
        plus ₱5 for each successfully delivered parcel.
        Failed attempts and returned parcels do not earn delivery commission.
        Draft payroll records are not proof of payment.
    </div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/payroll.js') }}" defer></script>
@endpush
