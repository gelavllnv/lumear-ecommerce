@extends('layouts.seller')

@push('styles')
<style>
    .seller-waybill-action {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin-top: 8px;
        border: 1px solid #be6560;
        border-radius: 10px;
        padding: 10px 13px;
        background: linear-gradient(135deg, #a84247, #7c2c35);
        color: #fff7ed;
        box-shadow: 0 9px 18px rgba(115, 39, 46, .28);
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }
    .seller-waybill-action:hover { background: #bd5054; color: #fff; }
    .seller-waybill-action span:first-child { font-size: 17px; }
    .seller-waybill-action small { display: block; margin-top: 2px; color: #f5c6af; font-size: 9px; font-weight: 600; }
</style>
@endpush

@section('seller-content')
    <div class="seller-page">
    <a href="{{ route('seller.pickup-tasks') }}" class="seller-waybill-action">Confirm van pickups</a>
    <header class="seller-page-header">
        <div><p class="seller-page-eyebrow">Fulfillment desk</p><h1>Order management</h1><p>Update the preparation and delivery status of every customer order.</p></div>
        <span class="seller-table-status">{{ $sellerOrders->total() }} order{{ $sellerOrders->total() === 1 ? '' : 's' }}</span>
    </header>

    <form class="seller-operation-card seller-operation-form mb-5 grid gap-3 sm:grid-cols-4" method="get"><label class="mini-label">Status<select class="field" name="status"><option value="">All statuses</option>@foreach(['pending' => 'New','accepted' => 'Accepted','packed' => 'Packed','ready_to_ship' => 'Ready for pickup','shipped' => 'Shipping','delivered' => 'Delivered','completed' => 'Completed','cancelled' => 'Cancelled'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label><label class="mini-label">Search order<input class="field" name="q" value="{{ request('q') }}" placeholder="Order reference"></label><label class="mini-label">From<input class="field" type="date" name="from" value="{{ request('from') }}"></label><label class="mini-label">To<input class="field" type="date" name="to" value="{{ request('to') }}"></label><div class="sm:col-span-4"><button class="rounded-xl px-4 py-2 text-sm font-bold">Apply filters</button></div></form>

    <div class="seller-list-stack">
        @forelse ($sellerOrders as $sellerOrder)
            @php($nextStatuses = $allowedTransitions[$sellerOrder->status] ?? [])
            <article class="seller-operation-card seller-order-card">
                <div class="flex flex-wrap justify-between gap-4">
                    <div><p class="seller-page-eyebrow">Order reference</p><h2 class="!mt-1 !text-xl">{{ $sellerOrder->order->reference }}</h2><p class="mt-1 text-xs text-maroon-900/55">Customer: {{ $sellerOrder->order->buyer->name }}</p><div class="mt-4 space-y-1">@foreach ($sellerOrder->items as $item)<p class="text-sm">{{ $item->product_name }} <span class="text-maroon-900/55">· {{ $item->variant_name }} × {{ $item->quantity }}</span></p>@endforeach</div></div>
                    <div class="flex flex-col items-start gap-2 text-xs sm:items-end">
                        <span class="rounded-full bg-cream-200 px-3 py-1.5 font-bold capitalize">{{ str_replace('_', ' ', $sellerOrder->status) }}</span>
                        <span class="rounded-full bg-maroon-900/5 px-3 py-1.5 font-bold capitalize">Payment: {{ $sellerOrder->order->payment_status }}</span>
                        @if($sellerOrder->shipment)
                            <span class="rounded-full bg-emerald-50 px-3 py-1.5 font-bold text-emerald-900">{{ $sellerOrder->provider?->name }} · {{ $sellerOrder->shipment->tracking_code }}</span>
                            @if(!in_array($sellerOrder->status, ['pending', 'accepted', 'cancelled'], true))
                                <a class="seller-waybill-action" target="_blank" rel="noopener" href="{{ route('seller.orders.waybill', $sellerOrder) }}">
                                    <span>▣</span><span>Print waybill<small>QR pickup label</small></span>
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($nextStatuses)
                    <form class="seller-operation-form mt-5 flex flex-wrap items-center gap-3 border-t border-sand-400/25 pt-4" method="post" action="{{ route('seller.orders.update', $sellerOrder) }}">
                        @csrf
                        @method('PATCH')
                        <label class="text-xs font-bold" for="status-{{ $sellerOrder->id }}">Next fulfillment action</label>
                        <select id="status-{{ $sellerOrder->id }}" class="rounded-lg border border-sand-400/60 bg-cream-50 p-2 text-sm" name="status">
                            @foreach ($nextStatuses as $status)
                                <option value="{{ $status }}">{{ match($status) {'ready_to_ship' => 'Request courier pickup', default => ucfirst(str_replace('_', ' ', $status))} }}</option>
                            @endforeach
                        </select>
                        @if(in_array('ready_to_ship', $nextStatuses, true))
                            <label class="text-xs font-bold" for="provider-{{ $sellerOrder->id }}">Courier partner</label>
                            <select id="provider-{{ $sellerOrder->id }}" class="rounded-lg border border-sand-400/60 bg-cream-50 p-2 text-sm" name="logistics_provider_id">
                                <option value="">Choose approved logistics partner</option>
                                @foreach($approvedLogisticsProviders as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach
                            </select>
                        @endif
                        @if(in_array('cancelled', $nextStatuses, true))<label class="sr-only" for="reason-{{ $sellerOrder->id }}">Cancellation reason</label><input id="reason-{{ $sellerOrder->id }}" class="field min-w-52" name="reason" minlength="10" maxlength="300" placeholder="Reason required when cancelling">@endif
                        <button class="rounded-lg px-4 py-2 text-sm font-bold">Update order</button>
                    </form>
                @else
                    <p class="mt-5 border-t border-sand-400/25 pt-4 text-sm text-maroon-900/60">This order is {{ str_replace('_', ' ', $sellerOrder->status) }} and has no further seller actions.</p>
                @endif
            </article>
        @empty
            <div class="seller-surface seller-empty-state"><b>No orders yet.</b><p>Your new customer orders will appear here.</p></div>
        @endforelse
    </div>

    <div class="mt-6">{{ $sellerOrders->links() }}</div>
    </div>
@endsection
