@extends('layouts.logistics-head-admin')

@section('title', 'Head Admin Dashboard | LUMEAR Logistics')

@section('content')
    <div class="ha-eyebrow">LOGISTICS ADMINISTRATION / OVERVIEW</div>
    <h1 class="ha-title">Command Center</h1>
    <p class="ha-description">Welcome, {{ auth()->user()->name }}. Review hub applications, configure salary policies, fund payroll operations, and create management reports from one place.</p>

    @push('styles')
        <style>
            .ha-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-top:28px; }
            .ha-stat { padding:22px; border:1px solid var(--line); border-radius:16px; background:var(--ivory); }
            .ha-stat span { color:var(--muted); font-size:11px; font-weight:700; }
            .ha-stat strong { display:block; margin-top:11px; color:var(--maroon); font-size:32px; }
            .ha-table-link { color:var(--maroon); font-weight:800; }
            @media(max-width:900px){.ha-stats{grid-template-columns:repeat(2,1fr)}}
            @media(max-width:500px){.ha-stats{grid-template-columns:1fr}}
        </style>
    @endpush

    <section class="ha-stats">
        <div class="ha-stat"><span>Total Applications</span><strong>{{ $statistics['total'] }}</strong></div>
        <div class="ha-stat"><span>Pending Review</span><strong>{{ $statistics['pending'] }}</strong></div>
        <div class="ha-stat"><span>Approved Hubs</span><strong>{{ $statistics['approved'] }}</strong></div>
        <div class="ha-stat"><span>Rejected</span><strong>{{ $statistics['rejected'] }}</strong></div>
    </section>

    <section class="ha-panel">
        <h2>Recent Logistics Applications</h2>
        <p>Latest business applications awaiting Head Admin oversight.</p>
        <div class="ha-scroll" style="margin-top:18px">
            <table><thead><tr><th>Business</th><th>Applicant</th><th>Municipality</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
                @forelse($applications as $application)
                    <tr><td><a class="ha-table-link" href="{{ route('logistics.head-admin.applications.show', $application->id) }}">{{ $application->company_name }}</a></td><td>{{ $application->first_name }} {{ $application->last_name }}</td><td>{{ $application->city }}, {{ $application->province }}</td><td>{{ ucfirst($application->status) }}</td><td>{{ $application->created_at }}</td></tr>
                @empty
                    <tr><td colspan="5">No Logistics applications yet.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </section>
@endsection
