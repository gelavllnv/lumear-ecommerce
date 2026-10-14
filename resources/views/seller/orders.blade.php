@extends('layouts.seller')

@section('seller-content')
    <div class="mb-6 flex items-end justify-between"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-maroon-700">Fulfillment desk</p><h1 class="mt-2 font-display text-4xl">Orders</h1></div></div>

    <div class="space-y-4">
        @forelse ($sellerOrders as $sellerOrder)
            @php($nextStatuses = $allowedTransitions[$sellerOrder->status] ?? [])
            <article class="rounded-2xl bg-cream-50 p-5 shadow-soft">
                <div class="flex flex-wrap justify-between gap-4">
                    <div><p class="font-bold">{{ $sellerOrder->order->reference }}</p><p class="mt-1 text-xs text-maroon-900/55">Customer: {{ $sellerOrder->order->buyer->name }}</p><div class="mt-3 space-y-1">@foreach ($sellerOrder->items as $item)<p class="text-sm">{{ $item->product_name }} <span class="text-maroon-900/55">· {{ $item->variant_name }} × {{ $item->quantity }}</span></p>@endforeach</div></div>
                    <div class="flex flex-col items-start gap-2 text-xs sm:items-end"><span class="rounded-full bg-cream-200 px-3 py-1.5 font-bold capitalize">{{ str_replace('_', ' ', $sellerOrder->status) }}</span><span class="rounded-full bg-maroon-900/5 px-3 py-1.5 font-bold capitalize">Payment: {{ $sellerOrder->order->payment_status }}</span></div>
                </div>

                @if ($nextStatuses)
                    <form class="mt-5 flex flex-wrap items-center gap-3 border-t border-sand-400/25 pt-4" method="post" action="{{ route('seller.orders.update', $sellerOrder) }}">
                        @csrf
                        @method('PATCH')
                        <label class="text-xs font-bold" for="status-{{ $sellerOrder->id }}">Next fulfillment action</label>
                        <select id="status-{{ $sellerOrder->id }}" class="rounded-lg border border-sand-400/60 bg-cream-50 p-2 text-sm" name="status">
                            @foreach ($nextStatuses as $status)
                                <option value="{{ $status }}">{{ match($status) {'ready_to_ship' => 'Request courier pickup (development)', 'shipped' => 'Picked up by courier (development)', 'delivered' => 'Mark delivered', 'completed' => 'Complete order & release earnings', default => ucfirst(str_replace('_', ' ', $status))} }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-lg bg-maroon-700 px-4 py-2 text-sm font-bold text-cream-50">Update order</button>
                    </form>
                @else
                    <p class="mt-5 border-t border-sand-400/25 pt-4 text-sm text-maroon-900/60">This order is {{ str_replace('_', ' ', $sellerOrder->status) }} and has no further seller actions.</p>
                @endif
            </article>
        @empty
            <div class="rounded-2xl bg-cream-50 p-12 text-center shadow-soft">No orders yet.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $sellerOrders->links() }}</div>
@endsection
