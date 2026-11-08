@extends('layouts.logistics')

@section('title', 'Returns | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/riders.css') }}">
<style>
    .lr-grid { display: grid; gap: 20px; }
    .lr-panel { overflow: hidden; border: 1px solid rgba(153, 132, 132, .55); border-radius: 14px; background: rgba(40, 27, 29, .58); }
    .lr-panel-head { display: flex; align-items: start; justify-content: space-between; gap: 16px; padding: 20px; border-bottom: 1px solid rgba(153, 132, 132, .35); }
    .lr-panel-head h2 { margin: 3px 0 0; font-size: 20px; }
    .lr-panel-head p { margin: 6px 0 0; color: #c3adae; font-size: 13px; }
    .lr-eyebrow { margin: 0; color: #e4a69c; font-size: 10px; font-weight: 800; letter-spacing: .15em; }
    .lr-card { padding: 18px 20px; border-bottom: 1px solid rgba(153, 132, 132, .25); }
    .lr-card:last-child { border-bottom: 0; }
    .lr-meta { display: flex; flex-wrap: wrap; gap: 8px 16px; margin: 9px 0 0; color: #cbb9b9; font-size: 13px; }
    .lr-status { display: inline-flex; border-radius: 999px; padding: 5px 9px; background: rgba(166, 70, 74, .24); color: #f2c6bf; font-size: 11px; font-weight: 800; text-transform: capitalize; }
    .lr-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; align-items: end; margin-top: 16px; }
    .lr-form label { display: grid; gap: 6px; color: #d7c4c4; font-size: 12px; font-weight: 700; }
    .lr-form select, .lr-form textarea { width: 100%; border: 1px solid #80686a; border-radius: 8px; padding: 10px; background: #211719; color: #f7eeee; font: inherit; }
    .lr-form textarea { min-height: 42px; resize: vertical; }
    .lr-wide { grid-column: 1 / -1; }
    .lr-message { margin-bottom: 18px; border: 1px solid rgba(202, 114, 99, .7); border-radius: 10px; padding: 14px 16px; color: #f3d6d0; }
    .lr-empty { padding: 28px 20px; color: #bfaeae; text-align: center; }
    .lr-action { border: 0; border-radius: 8px; padding: 11px 14px; background: #ae4b4d; color: #fff8f5; font-weight: 800; cursor: pointer; }
    .lr-action:hover { background: #c85c5d; }
    .lr-receive { margin-top: 12px; border: 1px solid #b76458; border-radius: 8px; padding: 9px 12px; background: transparent; color: #f2c0b7; font-weight: 800; cursor: pointer; }
    @media (max-width: 800px) { .lr-form { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="rm-page">
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">LOGISTICS / AFTER-SALES</p>
            <h1>Return logistics</h1>
            <p>Bring approved buyer returns back from pickup through hub receipt and final delivery to the seller.</p>
        </div>
        <div class="rm-header-actions">
            <a class="rm-button rm-button-outline" href="{{ route('logistics.transfers.index') }}">Hub transfers</a>
        </div>
    </header>

    @if (session('success'))
        <div class="lr-message" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="lr-message" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="lr-grid">
        <section class="lr-panel">
            <div class="lr-panel-head">
                <div><p class="lr-eyebrow">STEP 1</p><h2>Assign buyer return pickup</h2><p>Approved returns that still need collection from the buyer.</p></div>
                <span class="lr-status">{{ $waitingReturns->count() }} waiting</span>
            </div>
            @forelse ($waitingReturns as $return)
                <article class="lr-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <strong>{{ $return->reference }} · {{ $return->product_name }}</strong>
                            <div class="lr-meta"><span>Buyer: {{ $return->buyer_name }}</span><span>{{ $return->buyer_phone }}</span><span>{{ $return->pickup_address }}</span></div>
                            <div class="lr-meta"><span>Reason: {{ $return->reason }}</span><span>{{ $return->description }}</span></div>
                        </div>
                        <span class="lr-status">{{ str_replace('_', ' ', $return->pickup_status) }}</span>
                    </div>
                    <form class="lr-form" method="post" action="{{ route('logistics.returns.pickups.store') }}">
                        @csrf
                        <input type="hidden" name="return_request_id" value="{{ $return->id }}">
                        <label>Delivery rider
                            <select name="rider_id" required>
                                <option value="">Choose an available rider</option>
                                @foreach ($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->rider_name }} · {{ $rider->vehicle_type ?? 'Rider' }}</option>@endforeach
                            </select>
                        </label>
                        <label class="lr-wide">Pickup note (optional)<textarea name="note" maxlength="500" placeholder="Instructions for the rider or buyer"></textarea></label>
                        <button class="lr-action" type="submit">Assign pickup</button>
                    </form>
                </article>
            @empty
                <p class="lr-empty">No approved buyer returns are waiting for pickup.</p>
            @endforelse
        </section>

        <section class="lr-panel">
            <div class="lr-panel-head">
                <div><p class="lr-eyebrow">STEP 2</p><h2>Pickup and hub receipt</h2><p>Confirm a collected return when it is physically received at this hub.</p></div>
                <span class="lr-status">{{ $pickupTasks->total() }} task{{ $pickupTasks->total() === 1 ? '' : 's' }}</span>
            </div>
            @forelse ($pickupTasks as $task)
                <article class="lr-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <strong>{{ $task->reference }} · {{ $task->product_name }}</strong>
                            <div class="lr-meta"><span>Rider: {{ $task->rider_name }}</span><span>Leg: {{ str_replace('_', ' ', $task->return_leg ?? 'pickup') }}</span><span>{{ $task->reason }}</span></div>
                        </div>
                        <span class="lr-status">{{ str_replace('_', ' ', $task->status) }}</span>
                    </div>
                    @if (($task->return_leg ?? 'pickup') === 'pickup' && $task->status === 'collected')
                        <form method="post" action="{{ route('logistics.returns.pickups.receive', $task->id) }}">
                            @csrf
                            <button class="lr-receive" type="submit">Confirm hub receipt</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="lr-empty">No return pickup tasks yet.</p>
            @endforelse
            @if ($pickupTasks->hasPages())<div class="p-4">{{ $pickupTasks->links() }}</div>@endif
        </section>

        <section class="lr-panel">
            <div class="lr-panel-head">
                <div><p class="lr-eyebrow">STEP 3</p><h2>Send return back to seller</h2><p>Use a local delivery rider for this hub, or a hub-transfer rider when the seller is served by another hub.</p></div>
                <span class="lr-status">{{ $returnDeliveries->count() }} ready</span>
            </div>
            @forelse ($returnDeliveries as $return)
                <article class="lr-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <strong>{{ $return->reference }} · {{ $return->product_name }}</strong>
                            <div class="lr-meta"><span>Seller: {{ $return->seller_name }}</span><span>{{ $return->seller_phone }}</span><span>{{ collect([$return->seller_address, $return->seller_city, $return->seller_province])->filter()->implode(', ') }}</span></div>
                            <div class="lr-meta"><span>Waybill: {{ $return->tracking_code }}</span><span>Current: {{ str_replace('_', ' ', $return->delivery_status) }}</span></div>
                        </div>
                        <span class="lr-status">{{ str_replace('_', ' ', $return->delivery_status) }}</span>
                    </div>
                    <form class="lr-form" method="post" action="{{ route('logistics.returns.deliveries.store') }}">
                        @csrf
                        <input type="hidden" name="return_request_id" value="{{ $return->id }}">
                        <label>Destination hub
                            <select name="destination_provider_id" required>
                                <option value="">Choose seller's hub</option>
                                @foreach ($hubs as $hub)<option value="{{ $hub->id }}" @selected($return->return_destination_provider_id === $hub->id)>{{ $hub->name }} · {{ collect([$hub->municipality_name, $hub->province_name])->filter()->implode(', ') }}</option>@endforeach
                            </select>
                        </label>
                        <label>Rider
                            <select name="rider_id" required>
                                <option value="">Choose rider for this leg</option>
                                <optgroup label="Local delivery riders">
                                    @foreach ($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->rider_name }} · local delivery</option>@endforeach
                                </optgroup>
                                <optgroup label="Hub-transfer riders">
                                    @foreach ($handoverRiders as $rider)<option value="{{ $rider->id }}">{{ $rider->rider_name }} · hub transfer</option>@endforeach
                                </optgroup>
                            </select>
                        </label>
                        <label class="lr-wide">Delivery note (optional)<textarea name="note" maxlength="500" placeholder="Return handover or seller-delivery notes"></textarea></label>
                        <button class="lr-action" type="submit">Assign return delivery</button>
                    </form>
                </article>
            @empty
                <p class="lr-empty">No received returns are ready for the trip back to a seller.</p>
            @endforelse
        </section>
    </div>
</div>
@endsection
