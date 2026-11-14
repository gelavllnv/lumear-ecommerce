@extends('layouts.logistics')

@section('title', 'Returns | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/riders.css') }}">
<style>
    .lr-stack { display: grid; gap: 16px; }
    .lr-card { padding: 20px; border: 1px solid #49363d; border-radius: 14px; background: #21191d; }
    .lr-card h3 { margin: 0; font-size: 1.1rem; }
    .lr-meta { display: grid; gap: 6px; margin-top: 12px; color: #bda4a7; }
    .lr-form { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(220px, 1.4fr) auto; align-items: end; gap: 14px; margin-top: 18px; }
    .lr-form label { display: grid; gap: 7px; color: #bda4a7; font-size: .9rem; }
    .lr-form select, .lr-form textarea { width: 100%; padding: 11px 12px; border: 1px solid #49363d; border-radius: 9px; background: #261c21; color: #f9efec; font: inherit; }
    .lr-form select option { background: #fff; color: #222; }
    .lr-form option { color: #222; }
    .lr-empty { padding: 24px; border: 1px dashed #49363d; border-radius: 12px; color: #bda4a7; }
    .lr-status { display: inline-block; padding: 5px 10px; border-radius: 99px; background: #2d2227; color: #f0c6bc; font-size: .85rem; }
    .lr-panel { margin-top: 24px; }
    .lr-table-wrap { overflow-x: auto; }
    .lr-table { width: 100%; border-collapse: collapse; }
    .lr-table th, .lr-table td { padding: 13px 12px; border-bottom: 1px solid #49363d; text-align: left; vertical-align: top; }
    .lr-table th { color: #bda4a7; font-size: .78rem; text-transform: uppercase; }
    .lr-success { margin-bottom: 16px; padding: 12px 16px; border-radius: 10px; background: #22352b; color: #c1ebd0; }
    .lr-stage { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 16px; color: #bda4a7; font-size: .82rem; }
    .lr-stage span { padding: 6px 10px; border-radius: 99px; background: #2d2227; }
    .lr-stage span.active { background: #743238; color: #fff2ed; }
    .lr-assignment-form { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(220px, 1fr) minmax(220px, 1.3fr) auto; gap: 12px; align-items: end; margin-top: 18px; }
    .lr-assignment-form label { display: grid; gap: 7px; color: #bda4a7; font-size: .9rem; }
    .lr-assignment-form select, .lr-assignment-form textarea { width: 100%; padding: 11px 12px; border: 1px solid #49363d; border-radius: 9px; background: #261c21; color: #f9efec; font: inherit; }
    .lr-assignment-form option { color: #222; }
    @media (max-width: 900px) { .lr-assignment-form { grid-template-columns: 1fr; } }
    @media (max-width: 760px) { .lr-form { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="rm-page">
    <header class="rm-header">
        <div>
            <p class="rm-eyebrow">LOGISTICS / AFTER-SALES</p>
            <h1>Returns</h1>
            <p>Assign a rider to collect the return, move it between hubs if needed, and deliver it back to the seller.</p>
        </div>
    </header>

    @if(session('success'))
        <div class="lr-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="rm-panel">
        <div class="rm-panel-header">
            <div>
                <h2>Approved returns awaiting pickup</h2>
                <p>These requests have been reviewed by the seller and are ready for rider assignment.</p>
            </div>
        </div>
        <div class="lr-stack p-4">
            @forelse($waitingReturns as $returnRequest)
                <article class="lr-card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="rm-eyebrow">Order {{ $returnRequest->reference }}</p>
                            <h3>{{ $returnRequest->product_name }}</h3>
                            <div class="lr-meta">
                                <span>Buyer: {{ $returnRequest->buyer_name }} · {{ $returnRequest->buyer_phone ?: 'No phone provided' }}</span>
                                <span>Pickup address: {{ $returnRequest->pickup_address ?: 'Buyer address is unavailable' }}</span>
                                <span>Reason: {{ $returnRequest->reason }}</span>
                                <span>{{ $returnRequest->description }}</span>
                            </div>
                        </div>
                        <span class="lr-status">{{ str_replace('_', ' ', $returnRequest->pickup_status) }}</span>
                    </div>

                    <form class="lr-form" method="POST" action="{{ route('logistics.tasks.return-pickups.store') }}">
                        @csrf
                        <input type="hidden" name="return_request_id" value="{{ $returnRequest->id }}">
                        <label>
                            Pickup rider
                            <select name="rider_id" required>
                                <option value="">Select an available delivery rider</option>
                                @foreach($riders as $rider)
                                    <option value="{{ $rider->id }}">{{ $rider->rider_name }} · {{ $rider->vehicle_type }} / {{ $rider->plate_no }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Pickup instructions
                            <textarea name="note" rows="2" maxlength="500" placeholder="Optional instructions for the rider"></textarea>
                        </label>
                        <button class="rm-button rm-button-primary" type="submit" @disabled($riders->isEmpty())>Assign pickup</button>
                    </form>
                    @if($riders->isEmpty())
                        <p class="mt-3 text-sm text-amber-200">No available Delivery riders are registered to this hub right now.</p>
                    @endif
                </article>
            @empty
                <div class="lr-empty">No seller-approved returns are waiting for pickup.</div>
            @endforelse
        </div>
    </section>

    <section class="rm-panel lr-panel">
        <div class="rm-panel-header">
            <div>
                <h2>Returns ready to send to the seller</h2>
                <p>Choose the seller’s hub. A different hub uses a long-distance truck first; the destination hub then assigns a local delivery rider.</p>
            </div>
        </div>
        <div class="lr-stack p-4">
            @forelse($returnDeliveries as $returnDelivery)
                <article class="lr-card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="rm-eyebrow">RETURN · ORDER {{ $returnDelivery->reference }}</p>
                            <h3>{{ $returnDelivery->product_name }}</h3>
                            <div class="lr-meta">
                                <span>Seller: {{ $returnDelivery->seller_name }} · {{ $returnDelivery->seller_phone ?: 'No phone provided' }}</span>
                                <span>Seller address: {{ collect([$returnDelivery->seller_address, $returnDelivery->seller_city, $returnDelivery->seller_province])->filter()->implode(', ') ?: 'Address unavailable' }}</span>
                                <span>Reason: {{ $returnDelivery->reason }} · {{ $returnDelivery->description }}</span>
                                <span>Current stage: {{ str_replace('_', ' ', $returnDelivery->delivery_status) }}</span>
                            </div>
                        </div>
                        <span class="lr-status">{{ str_replace('_', ' ', $returnDelivery->delivery_status) }}</span>
                    </div>
                    <div class="lr-stage" aria-label="Return route progress">
                        <span class="active">Buyer return received at hub</span>
                        <span>Truck transfer if another hub</span>
                        <span>Local rider to seller</span>
                        <span>Seller received</span>
                    </div>
                    <form class="lr-assignment-form" method="POST" action="{{ route('logistics.returns.delivery.store') }}" data-current-hub="{{ $providerId }}" data-return-stage="{{ $returnDelivery->delivery_status }}">
                        @csrf
                        <input type="hidden" name="return_request_id" value="{{ $returnDelivery->id }}">
                        @if($returnDelivery->delivery_status === 'at_destination_hub')
                            <input type="hidden" name="destination_provider_id" value="{{ $providerId }}">
                            <label>Seller’s hub
                                <select disabled><option>Current hub · local seller delivery</option></select>
                            </label>
                        @else
                            <label>Seller’s destination hub
                                <select name="destination_provider_id" class="lr-destination-hub" required>
                                    <option value="">Select the seller’s hub</option>
                                    @foreach($hubs as $hubOption)
                                        <option value="{{ $hubOption->id }}" @selected($returnDelivery->return_destination_provider_id == $hubOption->id)>
                                            {{ $hubOption->name }} · {{ $hubOption->municipality_name }}, {{ $hubOption->province_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label>Vehicle / rider
                            <select name="rider_id" class="lr-return-rider" required>
                                <option value="">Select a rider</option>
                                @foreach($riders as $rider)
                                    <option value="{{ $rider->id }}" data-job="{{ \App\Support\RiderJobType::DELIVERY }}">Local · {{ $rider->rider_name }} · {{ $rider->vehicle_type }} / {{ $rider->plate_no }}</option>
                                @endforeach
                                @foreach($handoverRiders as $rider)
                                    <option value="{{ $rider->id }}" data-job="{{ \App\Support\RiderJobType::HUB_TRANSFER }}">Truck transfer · {{ $rider->rider_name }} · {{ $rider->vehicle_type }} / {{ $rider->plate_no }}</option>
                                @endforeach
                            </select>
                            <small class="lr-rider-help" role="status"></small>
                        </label>
                        <label>Instructions
                            <textarea name="note" rows="2" maxlength="500" placeholder="Optional return delivery instructions"></textarea>
                        </label>
                        <button class="rm-button rm-button-primary" type="submit" data-return-submit>Assign return</button>
                    </form>
                </article>
            @empty
                <div class="lr-empty">No returned parcels are waiting for seller delivery assignment at this hub.</div>
            @endforelse
        </div>
    </section>

    <section class="rm-panel lr-panel">
        <div class="rm-panel-header">
            <div>
                <h2>Return task activity</h2>
                <p>Review the current rider task and pickup receipt state for each return.</p>
            </div>
        </div>
        <div class="lr-table-wrap">
            <table class="lr-table">
                <thead>
                    <tr><th>Order / item</th><th>Reason</th><th>Current rider</th><th>Return status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse($pickupTasks as $task)
                        <tr>
                            <td><strong>{{ $task->reference }}</strong><br>{{ $task->product_name }}</td>
                            <td>{{ $task->reason }}<br><span class="text-sm text-maroon-900/60">{{ $task->description }}</span></td>
                            <td>{{ $task->rider_name }}</td>
                            <td><span class="lr-status">{{ str_replace('_', ' ', $task->delivery_status) }}</span><br><small>Pickup: {{ str_replace('_', ' ', $task->pickup_status) }} · Task: {{ ucfirst($task->status) }}</small></td>
                            <td>
                                @if(($task->return_leg === 'pickup' || $task->return_leg === null) && $task->status === 'collected' && $task->pickup_status === 'collected')
                                    <form method="POST" action="{{ route('logistics.tasks.return-pickups.receive', $task->id) }}" onsubmit="return confirm('Confirm this returned item has physically arrived at your hub?');">
                                        @csrf
                                        <button class="rm-button rm-button-primary" type="submit">Confirm hub receipt</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-5 text-center text-maroon-900/60">No return pickups have been assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lr-pagination p-4">{{ $pickupTasks->links() }}</div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.lr-assignment-form').forEach((form) => {
        const destination = form.querySelector('.lr-destination-hub');
        const rider = form.querySelector('.lr-return-rider');
        const button = form.querySelector('[data-return-submit]');
        const riderHelp = form.querySelector('.lr-rider-help');
        const currentHub = form.dataset.currentHub;
        const atDestinationHub = form.dataset.returnStage === 'at_destination_hub';

        const updateRiderChoices = () => {
            const isTruckLeg = !atDestinationHub && destination?.value && destination.value !== currentHub;
            const expectedJob = isTruckLeg ? 'Long-distance Handover' : 'Delivery';

            Array.from(rider.options).forEach((option) => {
                option.hidden = option.value !== '' && option.dataset.job !== expectedJob;
            });

            if (rider.selectedOptions[0]?.hidden) rider.value = '';
            const matchingRiders = Array.from(rider.options).filter((option) =>
                option.value !== '' && option.dataset.job === expectedJob
            );
            riderHelp.textContent = matchingRiders.length === 0
                ? (isTruckLeg
                    ? 'No available Long-distance Handover rider is registered at this hub. Register or enable a truck-transfer rider, then refresh this page.'
                    : 'No available Delivery rider is registered at this hub. Register or enable a delivery rider, then refresh this page.')
                : '';
            button.textContent = atDestinationHub
                ? 'Assign seller delivery'
                : isTruckLeg
                    ? 'Assign truck transfer'
                    : 'Assign local delivery';
            button.disabled = (!destination?.value && !atDestinationHub)
                || matchingRiders.length === 0
                || !rider.value;
        };

        destination?.addEventListener('change', updateRiderChoices);
        rider.addEventListener('change', updateRiderChoices);
        updateRiderChoices();
    });
});
</script>
@endpush
