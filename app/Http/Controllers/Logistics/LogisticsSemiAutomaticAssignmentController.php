<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\SemiAutomaticDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsSemiAutomaticAssignmentController extends Controller
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

    public function index(
        SemiAutomaticDeliveryService $service
    ) {
        return $this->page($service);
    }

    public function preview(
        Request $request,
        SemiAutomaticDeliveryService $service
    ) {
        $data = $request->validate([
            'shipment_ids' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],
            'shipment_ids.*' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],
        ]);

        $plan = $service->preview(
            $this->providerId(),
            $data['shipment_ids']
        );

        return $this->page($service, $plan);
    }

    private function page(
        SemiAutomaticDeliveryService $service,
        ?array $plan = null
    ) {
        $queue = $service
            ->queue($this->providerId())
            ->paginate(25)
            ->withQueryString();

        return view(
            'logistics.assignment.semi-automatic',
            compact('queue', 'plan')
        );
    }

    public function confirm(
        Request $request,
        SemiAutomaticDeliveryService $service
    ) {
        $data = $request->validate([
            'assignments' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],
            'assignments.*.shipment_id' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],
            'assignments.*.rider_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $count = $service->confirm(
            $this->providerId(),
            $data['assignments']
        );

        return redirect()
            ->route('logistics.semi-assignment.index')
            ->with(
                'success',
                $count
                    .' parcel(s) assigned. Dispatch them separately after rider handover.'
            );
    }
}
