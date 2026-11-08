@extends('layouts.logistics')

@section('title', 'Logistics Operations — LUMEAR')

@section('content')

@php
    $titles = [
        'incoming' => 'Incoming Parcels',
        'sorting' => 'Parcel Sorting',
        'assignment' => 'Delivery Assignment',
        'monitoring' => 'Delivery Monitoring',
        'rider-applications' => 'Rider Applications',
        'riders' => 'Rider List',
        'messages' => 'Messages',
        'reports' => 'Generate Reports',
        'account' => 'My Account',
    ];
@endphp

<style>
.ops-wrap {
    display: grid;
    gap: 20px;
}
.ops-panel {
    background: var(--card-bg, #241b1d);
    border: 1px solid #ffffff20;
    border-radius: 16px;
    padding: 22px;
}
.ops-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.ops-table th,
.ops-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ffffff20;
}
.ops-scroll {
    overflow-x: auto;
}
.ops-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.ops-form input,
.ops-form select,
.ops-form textarea {
    border: 1px solid #9996;
    border-radius: 8px;
    padding: 10px;
    background: transparent;
    color: inherit;
    max-width: 100%;
}
.ops-form select option {
    color: #111;
}
.ops-btn {
    border: 0;
    border-radius: 8px;
    padding: 10px 15px;
    background: #873632;
    color: white;
    cursor: pointer;
}
.ops-btn:hover {
    background: #62191c;
}
.ops-note {
    opacity: .7;
    font-size: 13px;
}
.ops-success,
.ops-error {
    padding: 12px;
    border-radius: 8px;
}
.ops-success {
    border: 1px solid #65aa78;
}
.ops-error {
    border: 1px solid #ce6767;
}
</style>

<div class="ops-wrap">

    <div>
        <span class="page-eyebrow">
            LOGISTICS OPERATIONS
        </span>

        <h1>{{ $titles[$module] }}</h1>

        <p class="ops-note">
            {{ $provider->name }} —
            {{ $provider->municipality_name }}
        </p>
    </div>

    @if(session('success'))
        <div class="ops-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="ops-error">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if(in_array($module, [
        'incoming', 'sorting', 'assignment',
        'monitoring', 'reports'
    ]))

        <div class="ops-panel">
            <div class="ops-form">
                @foreach($counts as $key => $value)
                    <span>
                        {{ ucwords(str_replace('_', ' ', $key)) }}:
                        <strong>{{ $value }}</strong>
                    </span>
                @endforeach
            </div>
        </div>

        @if($module === 'reports')

            <div class="ops-panel">
                <h2>Export Shipment Report</h2>

                <form
                    method="GET"
                    action="{{ route('logistics.ops.report') }}"
                    class="ops-form"
                >
                    <label>
                        From
                        <input type="date" name="from" required>
                    </label>

                    <label>
                        To
                        <input type="date" name="to" required>
                    </label>

                    <button class="ops-btn" type="submit">
                        Export CSV
                    </button>
                </form>
            </div>

        @else

            <div class="ops-panel">
                <form method="GET" class="ops-form">
                    <input
                        name="search"
                        value="{{ $search }}"
                        placeholder="Tracking or order reference"
                    >

                    <select name="status">
                        <option value="all">All statuses</option>

                        @foreach([
                            'unassigned', 'assigned', 'picked_up',
                            'in_transit', 'out_for_delivery',
                            'delivered', 'failed', 'returned'
                        ] as $option)
                            <option
                                value="{{ $option }}"
                                @selected($status === $option)
                            >
                                {{ ucwords(str_replace('_', ' ', $option)) }}
                            </option>
                        @endforeach
                    </select>

                    <button class="ops-btn">
                        Search
                    </button>
                </form>
            </div>

            <div class="ops-panel ops-scroll">

                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Tracking</th>
                            <th>Order</th>
                            <th>Seller</th>
                            <th>Status</th>
                            <th>Rider</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td>{{ $record->tracking_code }}</td>
                                <td>{{ $record->reference }}</td>
                                <td>{{ $record->seller_name ?? '—' }}</td>
                                <td>
                                    {{ ucwords(str_replace('_', ' ', $record->status)) }}
                                </td>
                                <td>
                                    {{ $record->rider_name ?? 'Unassigned' }}
                                </td>
                                <td>

                                    @if($module === 'incoming')

                                        <form
                                            method="POST"
                                            action="{{ route('logistics.ops.receive', $record->id) }}"
                                        >
                                            @csrf
                                            <button class="ops-btn">
                                                Receive
                                            </button>
                                        </form>

                                    @elseif($module === 'sorting')

                                        <form
                                            method="POST"
                                            action="{{ route('logistics.ops.sort', $record->id) }}"
                                        >
                                            @csrf
                                            <button class="ops-btn">
                                                Mark Sorted
                                            </button>
                                        </form>

                                    @elseif($module === 'assignment')

                                        <form
                                            method="POST"
                                            action="{{ route('logistics.ops.assign', $record->id) }}"
                                            class="ops-form"
                                        >
                                            @csrf

                                            <select name="rider_id" required>
                                                <option value="">
                                                    Choose rider
                                                </option>

                                                @foreach($riders->where('is_active', 1) as $rider)
                                                    <option
                                                        value="{{ $rider->id }}"
                                                        @selected($record->rider_id == $rider->id)
                                                    >
                                                        {{ $rider->name }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <button class="ops-btn">
                                                Assign
                                            </button>
                                        </form>

                                    @else

                                        <span class="ops-note">
                                            {{ $record->updated_at }}
                                        </span>

                                    @endif

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    No matching shipments found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>

        @endif

    @elseif($module === 'riders')

        <div class="ops-panel ops-scroll">

            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Email</th>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($riders as $rider)
                        <tr>
                            <td>{{ $rider->name }}</td>
                            <td>{{ $rider->email }}</td>
                            <td>{{ $rider->vehicle_type }}</td>
                            <td>{{ $rider->plate_no }}</td>
                            <td>
                                {{ $rider->is_active ? 'Active' : 'Inactive' }}
                            </td>
                            <td>
                                <form
                                    method="POST"
                                    action="{{ route('logistics.ops.rider-status', $rider->id) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="{{ $rider->is_active ? 0 : 1 }}"
                                    >

                                    <button class="ops-btn">
                                        {{ $rider->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                No riders registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>

    @elseif($module === 'rider-applications')

        <div class="ops-panel">

            <h2>Submit Rider Application</h2>

            <p class="ops-note">
                The rider must have an existing LUMEAR
                user account before approval.
            </p>

            <form
                method="POST"
                action="{{ route('logistics.ops.apply') }}"
                class="ops-form"
            >
                @csrf

                <input name="name" placeholder="Full name" required>
                <input name="email" type="email" placeholder="Registered email" required>
                <input name="vehicle_type" placeholder="Vehicle type" required>
                <input name="plate_no" placeholder="Plate number" required>

                <button class="ops-btn">
                    Submit
                </button>
            </form>

        </div>

        <div class="ops-panel ops-scroll">

            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Decision</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($applications as $application)
                        <tr>
                            <td>{{ $application->name }}</td>
                            <td>{{ $application->email }}</td>
                            <td>{{ $application->vehicle_type }}</td>
                            <td>{{ ucfirst($application->status) }}</td>
                            <td>
                                @if($application->status === 'pending')
                                    <form
                                        method="POST"
                                        action="{{ route('logistics.ops.decide', $application->id) }}"
                                        class="ops-form"
                                    >
                                        @csrf

                                        <button
                                            class="ops-btn"
                                            name="decision"
                                            value="approved"
                                        >
                                            Approve
                                        </button>

                                        <button
                                            class="ops-btn"
                                            name="decision"
                                            value="rejected"
                                        >
                                            Reject
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No rider applications.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>

    @elseif($module === 'messages')

        <div class="ops-panel">

            <h2>Send Message</h2>

            <form
                method="POST"
                action="{{ route('logistics.ops.message') }}"
                class="ops-form"
            >
                @csrf

                <input
                    type="email"
                    name="recipient_email"
                    placeholder="Recipient account email"
                    required
                >

                <textarea
                    name="body"
                    rows="3"
                    placeholder="Write a message"
                    required
                ></textarea>

                <button class="ops-btn">
                    Send
                </button>
            </form>

        </div>

        <div class="ops-panel">

            <h2>Messages</h2>

            @forelse($messages as $message)
                <p>
                    <strong>
                        {{ $message->sender_name ?? 'Unknown sender' }}
                        →
                        {{ $message->recipient_name ?? 'Unknown recipient' }}
                    </strong>

                    <small>{{ $message->created_at }}</small>
                </p>

                <p>{{ $message->body }}</p>

                <hr style="opacity:.2;margin:15px 0;">

            @empty
                <p>No messages yet.</p>
            @endforelse

        </div>

    @elseif($module === 'account')

        <div class="ops-panel">

            <h2>Business Information</h2>

            <p>
                {{ $provider->municipality_name }},
                {{ $provider->province_name }}
                · {{ ucfirst($provider->status) }}
            </p>

            <form
                method="POST"
                action="{{ route('logistics.ops.account') }}"
                class="ops-form"
            >
                @csrf

                <input
                    name="name"
                    value="{{ $provider->name }}"
                    placeholder="Business name"
                    required
                >

                <input
                    name="contact_phone"
                    value="{{ $provider->contact_phone }}"
                    placeholder="Contact number"
                    required
                >

                <button class="ops-btn">
                    Save Business Details
                </button>
            </form>

        </div>

        <div class="ops-panel">

            <h2>Change Password</h2>

            <form
                method="POST"
                action="{{ route('logistics.ops.password') }}"
                class="ops-form"
            >
                @csrf

                <input
                    type="password"
                    name="current_password"
                    placeholder="Current password"
                    required
                >

                <input
                    type="password"
                    name="password"
                    placeholder="New password"
                    required
                >

                <input
                    type="password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    required
                >

                <button class="ops-btn">
                    Update Password
                </button>
            </form>

        </div>

    @endif

</div>

@endsection
