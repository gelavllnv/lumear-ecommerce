<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\HubHandoverConfirmationService;
use App\Services\HubTransferService;
use App\Support\RiderJobType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsHubTransferController extends Controller
{
    private function providerId(): int
    {
        $id = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless(
            $id,
            403,
            'Approved logistics account required.'
        );

        return (int) $id;
    }

    public function index()
    {
        $providerId = $this->providerId();

        $tasks = DB::table('rider_tasks as t')
            ->join('shipments as s', 's.id', '=', 't.shipment_id')
            ->join('riders as r', 'r.id', '=', 't.rider_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join(
                'logistics_providers as origin',
                'origin.id',
                '=',
                't.logistics_provider_id'
            )
            ->join(
                'logistics_providers as destination',
                'destination.id',
                '=',
                't.destination_provider_id'
            )
            ->where('t.job_type', RiderJobType::HUB_TRANSFER)
            ->whereColumn(
                's.logistics_provider_id',
                't.logistics_provider_id'
            )
            ->whereColumn(
                'r.logistics_provider_id',
                't.logistics_provider_id'
            )
            ->where('r.job_type', RiderJobType::HUB_TRANSFER)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('logistics_hub_transfers as h')
                    ->whereColumn('h.rider_task_id', 't.id');
            })
            ->select(
                't.id',
                't.status as truck_status',
                's.tracking_code',
                'u.name as driver',
                'origin.name as origin',
                'destination.name as destination'
            );

        $pending = (clone $tasks)
            ->where('t.logistics_provider_id', $providerId)
            ->where('t.status', 'assigned')
            ->orderByDesc('t.id')
            ->paginate(15, ['*'], 'pending_page')
            ->withQueryString();

        $incomingTasks = (clone $tasks)
            ->where('t.destination_provider_id', $providerId)
            ->where('t.logistics_provider_id', '!=', $providerId)
            ->whereRaw(
                "LOWER(TRIM(COALESCE(t.status, ''))) <> ?",
                ['cancelled']
            )
            ->whereIn(
                's.status',
                ['unassigned', 'assigned', 'picked_up', 'in_transit']
            )
            ->orderByDesc('t.id')
            ->paginate(15, ['*'], 'incoming_page')
            ->withQueryString();

        $transfers = DB::table('logistics_hub_transfers as h')
            ->join('shipments as s', 's.id', '=', 'h.shipment_id')
            ->join(
                'logistics_providers as origin',
                'origin.id',
                '=',
                'h.source_provider_id'
            )
            ->join(
                'logistics_providers as destination',
                'destination.id',
                '=',
                'h.destination_provider_id'
            )
            ->where(function ($query) use ($providerId) {
                $query->where('h.source_provider_id', $providerId)
                    ->orWhere('h.destination_provider_id', $providerId);
            })
            ->select(
                'h.*',
                's.tracking_code',
                'origin.name as source_name',
                'destination.name as destination_name'
            )
            ->orderByDesc('h.id')
            ->paginate(15, ['*'], 'transfers_page')
            ->withQueryString();

        return view(
            'logistics.transfers.index',
            compact(
                'providerId',
                'pending',
                'incomingTasks',
                'transfers'
            )
        );
    }

    public function dispatch(int $id, HubTransferService $service)
    {
        $created = $service->dispatch(
            $id,
            $this->providerId(),
            (int) Auth::id()
        );

        return redirect()
            ->route('logistics.transfers.index')
            ->with(
                'success',
                $created
                    ? 'Transfer dispatched. The destination hub can now confirm receipt.'
                    : 'This task was already registered.'
            );
    }

    private function receiptData(Request $request): array
    {
        return $request->validate([
            'tracking_code' => ['required', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'physical_receipt' => ['accepted'],
        ]);
    }

    public function receive(
        Request $request,
        int $id,
        HubTransferService $service
    ) {
        $data = $this->receiptData($request);

        $received = $service->receive(
            $id,
            $this->providerId(),
            (int) Auth::id(),
            $data['tracking_code'],
            $data['condition'] ?? null,
            $data['remarks'] ?? null
        );

        return $this->receiptResponse($received);
    }

    public function receiveTask(
        Request $request,
        int $id,
        HubHandoverConfirmationService $service
    ) {
        $data = $this->receiptData($request);

        $received = $service->confirm(
            $id,
            $this->providerId(),
            (int) Auth::id(),
            $data['tracking_code'],
            $data['condition'] ?? null,
            $data['remarks'] ?? null
        );

        return $this->receiptResponse($received);
    }

    private function receiptResponse(bool $received)
    {
        return redirect()
            ->route('logistics.transfers.index')
            ->with(
                'success',
                $received
                    ? 'Physical receipt confirmed. The truck task is complete. Verify and sort the parcel at this hub.'
                    : 'This transfer was already received.'
            );
    }
}
