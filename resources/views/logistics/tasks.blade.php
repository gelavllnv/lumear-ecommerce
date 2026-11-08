@extends($layout)
@section('title', 'Pickup and hub transfers')
@section($logistics ? 'content' : 'seller-content')
<section class="p-6" id="riderTasks" data-base="{{ $taskBase }}" data-logistics="{{ $logistics ? '1' : '0' }}" data-csrf="{{ csrf_token() }}">
    <h1 class="text-2xl font-bold">{{ $logistics ? 'Rider pickup and hub transfers' : 'Confirm seller pickups' }}</h1>
    <p>Seller pickups require seller confirmation. Hub handovers require acceptance by the receiving logistics provider.</p>
    <p id="taskError" role="alert"></p>
    <button type="button" id="refreshTasks" class="rounded border p-2">Refresh</button>
    @if($logistics)
        <form id="assignTask" class="my-5 flex flex-wrap gap-3">
            <label>Parcel <select id="taskShipment" required></select></label>
            <label>Job <select id="taskJob"><option>Seller Pickup</option><option>Long-distance Handover</option></select></label>
            <label>Rider <select id="taskRider" required></select></label>
            <label>Destination hub <select id="taskHub"></select></label>
            <button class="rounded border p-2">Assign task</button>
        </form>
    @endif
    <div id="taskList" class="space-y-4"></div>
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/rider-tasks.js') }}" defer></script>
@endpush
