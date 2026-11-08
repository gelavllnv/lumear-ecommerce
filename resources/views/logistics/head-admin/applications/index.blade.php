@extends('layouts.logistics-head-admin')

@section('title', 'Hub Applications | LUMEAR Logistics')

@section('content')
    <div class="ha-eyebrow">HUB NETWORK / APPLICATIONS</div>
    <h1 class="ha-title">Hub Applications</h1>
    <p class="ha-description">Review pending registrations and keep a complete view of approved and rejected city or bayan Logistics hubs.</p>

    @push('styles')
        <style>
            .ha-filters { display:flex; flex-wrap:wrap; gap:9px; margin-top:26px; }
            .ha-filter { padding:10px 14px; border:1px solid var(--line); border-radius:9px; background:var(--ivory); color:var(--maroon); font-size:11px; font-weight:800; text-decoration:none; }
            .ha-filter.active { background:var(--maroon); border-color:var(--maroon); color:var(--ivory); }
            .ha-status { font-weight:800; text-transform:capitalize; }
            .ha-status.pending { color:#9b681b; }.ha-status.approved { color:#28704c; }.ha-status.rejected { color:#a12d38; }
            .ha-pagination { margin-top:18px; }
        </style>
    @endpush

    <nav class="ha-filters" aria-label="Application status filters">
        @foreach(['all', 'pending', 'approved', 'rejected'] as $filter)
            <a class="ha-filter {{ $status === $filter ? 'active' : '' }}" href="{{ route('logistics.head-admin.applications.index', ['status' => $filter]) }}">{{ ucfirst($filter) }}</a>
        @endforeach
    </nav>

    <section class="ha-panel">
        <h2>{{ ucfirst($status) }} Applications</h2>
        <p>Click Review to see supporting documents and the complete registration record.</p>
        <div class="ha-scroll" style="margin-top:18px">
            <table>
                <thead><tr><th>Business</th><th>Applicant</th><th>Municipality</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($applications as $application)
                        <tr>
                            <td><strong>{{ $application->company_name }}</strong></td>
                            <td>{{ $application->first_name }} {{ $application->last_name }}</td>
                            <td>{{ $application->city }}, {{ $application->province }}</td>
                            <td><span class="ha-status {{ $application->status }}">{{ ucfirst($application->status) }}</span></td>
                            <td>{{ \Carbon\Carbon::parse($application->created_at)->format('M d, Y') }}</td>
                            <td><a class="ha-button" href="{{ route('logistics.head-admin.applications.show', $application->id) }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No {{ $status === 'all' ? '' : $status }} applications found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($applications->hasPages())<div class="ha-pagination">{{ $applications->links() }}</div>@endif
    </section>
@endsection
