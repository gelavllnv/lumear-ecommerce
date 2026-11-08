@extends('layouts.logistics-head-admin')

@section('title', 'Head Admin Reports | LUMEAR Logistics')

@section('content')
    <div class="ha-eyebrow">LOGISTICS ADMINISTRATION / REPORTING</div>
    <h1 class="ha-title">Head Admin Reports</h1>
    <p class="ha-description">Generate an official PDF snapshot of city and bayan hubs, salary tiers, Hub Admin salaries, rider and driver payroll, and Central Fund activity.</p>

    @push('styles')
        <style>
            .ha-report-form { display:grid; grid-template-columns:1fr 1fr auto; gap:16px; align-items:end; margin-top:22px; }
            .ha-report-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:20px; }
            .ha-report-cards article { padding:18px; border:1px solid var(--line); border-radius:14px; background:var(--surface); }
            .ha-report-cards strong { display:block; color:var(--maroon); font-size:12px; margin-bottom:7px; }
            .ha-report-cards span { color:var(--muted); font-size:11px; line-height:1.6; }
            @media(max-width:700px){.ha-report-form,.ha-report-cards{grid-template-columns:1fr}}
        </style>
    @endpush

    <section class="ha-panel">
        <h2>Generate Financial and Hub Report</h2>
        <p>Current balances are included together with salary and payroll movement in the selected reporting period.</p>
        <form method="POST" action="{{ route('logistics.head-admin.reports.pdf') }}" class="ha-report-form">
            @csrf
            <label>From<input type="date" name="from" value="{{ now('Asia/Manila')->startOfMonth()->format('Y-m-d') }}" required></label>
            <label>To<input type="date" name="to" value="{{ now('Asia/Manila')->format('Y-m-d') }}" required></label>
            <button class="ha-button" type="submit">Download PDF Report</button>
        </form>
    </section>
    <section class="ha-report-cards"><article><strong>Registered Hubs</strong><span>Hub name, city/bayan, province, Hub Admin, and salary tier.</span></article><article><strong>Salary and Payroll</strong><span>Hub Admin salaries, rider/driver salaries, and remaining payroll funds.</span></article><article><strong>Central Fund</strong><span>Central balance, funding recorded, and allocations distributed to hubs.</span></article></section>
@endsection
