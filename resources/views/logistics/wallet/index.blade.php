@extends('layouts.logistics')

@section('title', 'LUMEAR E-Wallet | Logistics')

@push('styles')
<style>
    .wallet-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
        padding-bottom: 40px;
    }

    .wallet-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .wallet-heading h1 {
        font-family: 'DM Serif Display', serif;
        font-size: clamp(28px, 3vw, 36px);
        font-weight: 400;
        margin: 0 0 7px;
        color: var(--text-primary, #f4e8e2);
    }

    .wallet-heading p {
        margin: 0;
        color: var(--text-secondary, #ad9d9e);
        font-size: 13px;
        line-height: 1.7;
    }

    .wallet-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 13px;
        border-radius: 30px;
        background: rgba(47, 155, 105, .1);
        border: 1px solid rgba(47, 155, 105, .25);
        color: #69d6a1;
        font-size: 11px;
        font-weight: 700;
    }

    .wallet-live-badge::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #69d6a1;
    }

    .wallet-balance-card {
        position: relative;
        overflow: hidden;
        padding: clamp(25px, 4vw, 38px);
        border-radius: 20px;
        background: linear-gradient(
            125deg,
            #62191c 0%,
            #873632 58%,
            #9e7161 100%
        );
        border: 1px solid rgba(255,255,255,.12);
        color: #fff;
    }

    .wallet-balance-card::after {
        content: '';
        position: absolute;
        right: -65px;
        top: -95px;
        width: 300px;
        height: 300px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 50%;
        box-shadow:
            0 0 0 45px rgba(255,255,255,.035),
            0 0 0 90px rgba(255,255,255,.025);
        pointer-events: none;
    }

    .wallet-balance-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 35px;
        position: relative;
        z-index: 1;
    }

    .wallet-balance-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .wallet-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 43px;
        height: 43px;
        border-radius: 12px;
        background: rgba(255,255,255,.14);
    }

    .wallet-icon svg {
        width: 23px;
        height: 23px;
    }

    .wallet-balance-brand strong {
        display: block;
        font-size: 14px;
        letter-spacing: .3px;
    }

    .wallet-balance-brand span {
        display: block;
        font-size: 10px;
        opacity: .75;
        margin-top: 4px;
    }

    .wallet-currency {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1px;
        padding: 8px 12px;
        border: 1px solid rgba(255,255,255,.25);
        border-radius: 7px;
    }

    .wallet-balance-label {
        font-size: 12px;
        opacity: .8;
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
    }

    .wallet-balance-amount {
        font-size: clamp(38px, 5vw, 55px);
        font-weight: 800;
        letter-spacing: -2px;
        line-height: 1.2;
        position: relative;
        z-index: 1;
        overflow-wrap: anywhere;
    }

    .wallet-balance-footer {
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,.18);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }

    .wallet-balance-footer span {
        font-size: 11px;
        opacity: .85;
    }

    .wallet-balance-footer strong {
        font-size: 12px;
    }

    .wallet-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .wallet-stat-card,
    .wallet-panel {
        background: var(--card-bg, #241d20);
        border: 1px solid var(--border-color, rgba(202,174,159,.13));
        border-radius: 16px;
    }

    .wallet-stat-card {
        padding: 23px;
    }

    .wallet-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        background: rgba(135,54,50,.16);
        color: #d5a59a;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }

    .wallet-stat-icon svg {
        width: 20px;
        height: 20px;
    }

    .wallet-stat-label {
        color: var(--text-secondary, #ad9d9e);
        font-size: 11px;
        margin-bottom: 10px;
    }

    .wallet-stat-value {
        color: var(--text-primary, #f4e8e2);
        font-size: clamp(22px, 2.4vw, 29px);
        font-weight: 800;
        letter-spacing: -.8px;
        overflow-wrap: anywhere;
    }

    .wallet-stat-note {
        margin-top: 9px;
        color: var(--text-secondary, #ad9d9e);
        font-size: 10px;
    }

    .wallet-panel {
        overflow: hidden;
    }

    .wallet-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        flex-wrap: wrap;
        padding: 24px;
        border-bottom: 1px solid var(--border-color, rgba(202,174,159,.13));
    }

    .wallet-panel-header h2 {
        margin: 0 0 5px;
        color: var(--text-primary, #f4e8e2);
        font-size: 17px;
        font-weight: 800;
    }

    .wallet-panel-header p {
        margin: 0;
        color: var(--text-secondary, #ad9d9e);
        font-size: 11px;
    }

    .wallet-commission-pill {
        padding: 9px 13px;
        border-radius: 8px;
        background: rgba(135,54,50,.14);
        color: #d5a59a;
        font-size: 11px;
        font-weight: 800;
    }

    .wallet-table-wrapper {
        overflow-x: auto;
    }

    .wallet-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        white-space: nowrap;
    }

    .wallet-table th {
        padding: 16px 22px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .7px;
        color: var(--text-secondary, #ad9d9e);
        background: rgba(135,54,50,.045);
    }

    .wallet-table td {
        padding: 18px 22px;
        font-size: 12px;
        color: var(--text-primary, #f4e8e2);
        border-top: 1px solid var(--border-color, rgba(202,174,159,.1));
    }

    .wallet-table td small {
        display: block;
        margin-top: 5px;
        color: var(--text-secondary, #ad9d9e);
        font-size: 10px;
    }

    .wallet-positive {
        color: #69d6a1 !important;
        font-weight: 800;
    }

    .wallet-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 7px;
        background: rgba(47,155,105,.1);
        color: #69d6a1;
        font-size: 10px;
        font-weight: 700;
    }

    .wallet-empty {
        text-align: center;
        padding: 65px 20px;
    }

    .wallet-empty svg {
        width: 42px;
        height: 42px;
        color: #9e7161;
        margin-bottom: 15px;
    }

    .wallet-empty h3 {
        color: var(--text-primary, #f4e8e2);
        font-size: 16px;
        margin: 0 0 8px;
    }

    .wallet-empty p {
        color: var(--text-secondary, #ad9d9e);
        font-size: 12px;
        margin: 0;
        line-height: 1.7;
    }

    .wallet-pagination {
        padding: 18px 24px;
        border-top: 1px solid var(--border-color, rgba(202,174,159,.13));
    }

    .wallet-pagination nav {
        display: flex;
        justify-content: center;
    }

    .wallet-info {
        display: flex;
        gap: 14px;
        padding: 20px;
        border-radius: 12px;
        background: rgba(135,54,50,.08);
        border: 1px solid rgba(135,54,50,.15);
    }

    .wallet-info svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        color: #c99b91;
    }

    .wallet-info strong {
        display: block;
        color: var(--text-primary, #f4e8e2);
        font-size: 12px;
        margin-bottom: 6px;
    }

    .wallet-info p {
        color: var(--text-secondary, #ad9d9e);
        font-size: 11px;
        line-height: 1.8;
        margin: 0;
    }

    [data-theme="light"] .wallet-heading h1,
    [data-theme="light"] .wallet-stat-value,
    [data-theme="light"] .wallet-panel-header h2,
    [data-theme="light"] .wallet-table td,
    [data-theme="light"] .wallet-empty h3,
    [data-theme="light"] .wallet-info strong {
        color: #39282b;
    }

    [data-theme="light"] .wallet-stat-card,
    [data-theme="light"] .wallet-panel {
        background: #fffaf7;
        border-color: #e8d9d0;
    }

    [data-theme="light"] .wallet-heading p,
    [data-theme="light"] .wallet-stat-label,
    [data-theme="light"] .wallet-stat-note,
    [data-theme="light"] .wallet-panel-header p,
    [data-theme="light"] .wallet-table th,
    [data-theme="light"] .wallet-table td small,
    [data-theme="light"] .wallet-empty p,
    [data-theme="light"] .wallet-info p {
        color: #806d70;
    }

    [data-theme="light"] .wallet-positive,
    [data-theme="light"] .wallet-status {
        color: #168052 !important;
    }

    @media (max-width: 900px) {
        .wallet-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px) {
        .wallet-stats {
            grid-template-columns: 1fr;
        }

        .wallet-stat-card {
            padding: 20px;
        }

        .wallet-panel-header {
            padding: 20px;
        }

        .wallet-table th,
        .wallet-table td {
            padding: 15px;
        }
    }
</style>
@endpush

@section('content')

@php
    $formatMoney = fn ($minor) =>
        '₱' . number_format($minor / 100, 2);
@endphp

<div class="wallet-page">

    {{-- PAGE HEADING --}}
    <div class="wallet-heading">

        <div>
            <h1>LUMEAR E-Wallet</h1>

            <p>
                View your Hub Admin salary, Logistics commissions,
                and wallet transaction history.
            </p>
        </div>

        <div class="wallet-live-badge">
            MySQL Connected
        </div>

    </div>

    {{-- MAIN BALANCE CARD --}}
    <div class="wallet-balance-card">

        <div class="wallet-balance-top">

            <div class="wallet-balance-brand">

                <div class="wallet-icon">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <rect x="2" y="5" width="20" height="15" rx="3"/>
                        <path d="M16 13h6"/>
                        <path d="M5 5V3h14v2"/>
                    </svg>
                </div>

                <div>
                    <strong>LUMEAR Wallet</strong>
                    <span>Admin Salary and Commission Wallet</span>
                </div>

            </div>

            <div class="wallet-currency">PHP</div>

        </div>

        <div class="wallet-balance-label">
            Available Balance
        </div>

        <div class="wallet-balance-amount">
            {{ $formatMoney($availableBalanceMinor) }}
        </div>

        <div class="wallet-balance-footer">
            <span>
                {{ $provider->name ?? 'Logistics Provider' }}
            </span>

            <strong>
                Salary + 10% Commission Balance
            </strong>
        </div>

    </div>

    {{-- WALLET STATISTICS --}}
    <div class="wallet-stats">

        <div class="wallet-stat-card">

            <div class="wallet-stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M12 3v18"/>
                    <path d="m7 8 5-5 5 5"/>
                    <path d="M4 13v6h16v-6"/>
                </svg>
            </div>

            <div class="wallet-stat-label">
                Total Commission Earned
            </div>

            <div class="wallet-stat-value">
                {{ $formatMoney($totalEarningsMinor) }}
            </div>

            <div class="wallet-stat-note">
                All-time Logistics earnings
            </div>

        </div>

        <div class="wallet-stat-card">

            <div class="wallet-stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M12 3v18"/>
                    <path d="m7 8 5-5 5 5"/>
                    <path d="M4 13v6h16v-6"/>
                </svg>
            </div>

            <div class="wallet-stat-label">
                Salary Received
            </div>

            <div class="wallet-stat-value">
                {{ $formatMoney($totalSalaryMinor) }}
            </div>

            <div class="wallet-stat-note">
                Paid by the Logistics Head / Super Admin
            </div>

        </div>

        <div class="wallet-stat-card">

            <div class="wallet-stat-icon">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M3 10h18"/>
                    <path d="M8 3v4"/>
                    <path d="M16 3v4"/>
                </svg>
            </div>

            <div class="wallet-stat-label">
                This Month's Earnings
            </div>

            <div class="wallet-stat-value">
                {{ $formatMoney($thisMonthMinor) }}
            </div>

            <div class="wallet-stat-note">
                {{ now()->format('F Y') }}
            </div>

        </div>

    </div>

    {{-- TRANSACTION HISTORY --}}
    <div class="wallet-panel">

        <div class="wallet-panel-header">

            <div>
                <h2>Commission History</h2>
                <p>
                    Earnings automatically credited after
                    successful parcel deliveries.
                </p>
            </div>

            <div class="wallet-commission-pill">
                ₱5.00 / Delivery
            </div>

        </div>

        @if($transactions->count() > 0)

            <div class="wallet-table-wrapper">

                <table class="wallet-table">

                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Order / Waybill</th>
                            <th>Shipping Fee</th>
                            <th>Amount</th>
                            <th>Date Credited</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($transactions as $transaction)

                            @php
                                $isSalary = $transaction->type === 'hub_admin_salary';
                            @endphp

                            <tr>
                                <td>
                                    <strong>
                                        #{{ str_pad(
                                            $transaction->id,
                                            6,
                                            '0',
                                            STR_PAD_LEFT
                                        ) }}
                                    </strong>

                                    <small>
                                        {{ $isSalary
                                            ? 'Hub Admin Salary'
                                            : 'Delivery Commission' }}
                                    </small>
                                </td>

                                <td>
                                    <strong>
                                        @if($isSalary)
                                            {{ $transaction->reference }}
                                        @else
                                            {{ $transaction->order_reference ?? '—' }}
                                        @endif
                                    </strong>

                                    <small>
                                        @if($isSalary)
                                            Salary payment from Head / Super Admin
                                        @else
                                            {{ $transaction->tracking_code ?? '—' }}
                                        @endif
                                    </small>
                                </td>

                                <td>
                                    @if($isSalary)
                                        —
                                    @else
                                        {{ $formatMoney($transaction->shipping_fee_minor) }}
                                    @endif
                                </td>

                                <td class="wallet-positive">
                                    +{{ $formatMoney($transaction->amount_minor) }}
                                </td>

                                <td>
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $transaction->earned_at
                                    )->format('M d, Y') }}

                                    <small>
                                        {{ \Illuminate\Support\Carbon::parse(
                                            $transaction->earned_at
                                        )->format('h:i A') }}
                                    </small>
                                </td>

                                <td>
                                    <span class="wallet-status">
                                        Credited
                                    </span>
                                </td>
                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            @if($transactions->hasPages())
                <div class="wallet-pagination">
                    {{ $transactions->links() }}
                </div>
            @endif

        @else

            <div class="wallet-empty">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <rect x="2" y="5" width="20" height="15" rx="3"/>
                    <path d="M16 13h6"/>
                </svg>

                <h3>No commissions earned yet</h3>

                <p>
                    Your E-Wallet will automatically receive
                    ₱5.00 whenever an eligible shipment is
                    successfully marked as Delivered.
                </p>

            </div>

        @endif

    </div>

    {{-- COMMISSION POLICY --}}
    <div class="wallet-info">

        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
        >
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 11v6"/>
            <path d="M12 7h.01"/>
        </svg>

        <div>
            <strong>How your commission works</strong>

            <p>
                This wallet receives two kinds of Hub Admin earnings:
                salary paid by the Logistics Head / Super Admin and
                delivery commissions earned from successful deliveries.
                COD Remittance is separate and never becomes part of
                this balance. Rider, Seller Pickup Rider, and Hub-to-Hub
                Driver salaries are paid only from the protected Hub
                Rider Payroll Fund.
            </p>
        </div>

    </div>

</div>

@endsection
