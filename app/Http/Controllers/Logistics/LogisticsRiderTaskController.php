<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\RiderRemittanceService;
use App\Services\RiderTaskAssignmentService;
use App\Support\RiderJobType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LogisticsRiderTaskController extends Controller
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

    public function index(Request $request)
    {
        $providerId = $this->providerId();

        $jobType = RiderJobType::validateSelected(
            $request->query(
                'job_type',
                RiderJobType::SELLER_PICKUP
            )
        );

        $riders = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->where('r.job_type', $jobType)
            ->select(
                'r.*',
                'u.name as rider_name'
            )
            ->orderBy('u.name')
            ->get()
            ->filter(
                fn ($rider) =>
                    $rider->job_type === $jobType
                    && RiderJobType::isAvailable($rider)
            );

        $hubs = DB::table('logistics_providers')
            ->where('status', 'approved')
            ->where('id', '!=', $providerId)
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        $parcels = DB::table('shipments as s')
            ->join(
                'seller_orders as so',
                'so.id',
                '=',
                's.seller_order_id'
            )
            ->where('s.logistics_provider_id', $providerId)
            ->where('so.logistics_provider_id', $providerId)
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('rider_tasks as rt')
                    ->whereColumn('rt.shipment_id', 's.id')
                    ->where(function ($active) {
                        $active
                            ->whereNotIn(
                                'rt.status',
                                RiderTaskAssignmentService::TERMINAL_STATUSES
                            )
                            ->orWhereNull('rt.status');
                    });
            });

        if ($jobType === RiderJobType::SELLER_PICKUP) {
            $parcels
                ->where('so.status', 'ready_to_ship')
                ->where('s.status', 'unassigned')
                ->whereNull('s.rider_id')
                ->whereNotExists(function ($query) {
                    $query
                        ->selectRaw('1')
                        ->from('logistics_sorting as ls')
                        ->whereColumn('ls.shipment_id', 's.id');
                });
        } elseif ($jobType === RiderJobType::HUB_TRANSFER) {
            $parcels
                ->whereIn('s.status', [
                    'unassigned',
                    'picked_up',
                    'in_transit',
                ])
                ->whereNull('s.rider_id')
                ->whereExists(function ($query) use ($providerId) {
                    $query
                        ->selectRaw('1')
                        ->from('logistics_sorting as ls')
                        ->whereColumn('ls.shipment_id', 's.id')
                        ->where('ls.logistics_provider_id', $providerId)
                        ->where('ls.stage', 'sorted')
                        ->whereNotNull('ls.waybill_verified_at');
                });
        } else {
            // Customer delivery continues through the existing assignment page.
            $parcels->whereRaw('1 = 0');
        }

        $parcels = $parcels
            ->select(
                's.id',
                's.tracking_code',
                's.status'
            )
            ->orderByDesc('s.id')
            ->paginate(20, ['*'], 'parcels_page')
            ->withQueryString();

        $tasks = DB::table('rider_tasks as rt')
            ->join(
                'shipments as s',
                's.id',
                '=',
                'rt.shipment_id'
            )
            ->join(
                'riders as r',
                'r.id',
                '=',
                'rt.rider_id'
            )
            ->join(
                'users as u',
                'u.id',
                '=',
                'r.user_id'
            )
            ->leftJoin(
                'logistics_providers as destination',
                'destination.id',
                '=',
                'rt.destination_provider_id'
            )
            ->where('rt.logistics_provider_id', $providerId)
            ->where('rt.job_type', $jobType)
            ->select(
                'rt.*',
                's.tracking_code',
                'u.name as rider_name',
                'destination.name as destination_name'
            )
            ->orderByDesc('rt.id')
            ->paginate(20, ['*'], 'tasks_page')
            ->withQueryString();

        return view(
            'logistics.tasks.index',
            compact(
                'jobType',
                'riders',
                'hubs',
                'parcels',
                'tasks'
            )
        );
    }

    public function store(
        Request $request,
        RiderTaskAssignmentService $assignments,
        RiderRemittanceService $remittances
    ) {
        $providerId = $this->providerId();

        $data = $request->validate([
            'job_type' => [
                'required',
                Rule::in([
                    RiderJobType::SELLER_PICKUP,
                    RiderJobType::HUB_TRANSFER,
                ]),
            ],
            'shipment_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'rider_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'destination_provider_id' => [
                Rule::requiredIf(
                    $request->input('job_type')
                        === RiderJobType::HUB_TRANSFER
                ),
                'nullable',
                'integer',
                'min:1',
            ],
            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        DB::transaction(function () use (
            $providerId,
            $data,
            $assignments,
            $remittances
        ) {
            $rider = DB::table('riders')
                ->where('id', $data['rider_id'])
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $rider,
                422,
                'Rider not found in your logistics account.'
            );

            RiderJobType::ensureMatches(
                $rider,
                $data['job_type']
            );

            if (!$remittances->canAcceptNewAssignment(
                (int) $rider->id,
                $providerId
            )) {
                throw ValidationException::withMessages([
                    'rider_id' =>
                        'Rider is unavailable or restricted by COD remittance rules.',
                ]);
            }

            if (
                $data['job_type'] === RiderJobType::SELLER_PICKUP
                && !$remittances->canScanNewPickup(
                    (int) $rider->id,
                    $providerId
                )
            ) {
                throw ValidationException::withMessages([
                    'rider_id' =>
                        'Rider is restricted from seller pickups.',
                ]);
            }

            $shipment = DB::table('shipments')
                ->where('id', $data['shipment_id'])
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $shipment,
                404,
                'Shipment not found.'
            );

            $sellerOrder = DB::table('seller_orders')
                ->where('id', $shipment->seller_order_id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $sellerOrder,
                422,
                'Seller order does not belong to your logistics account.'
            );

            abort_if(
                $shipment->rider_id,
                422,
                'This parcel already has a customer-delivery rider.'
            );

            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $shipment->id)
                ->lockForUpdate()
                ->first();

            if ($data['job_type'] === RiderJobType::SELLER_PICKUP) {
                abort_unless(
                    $sellerOrder->status === 'ready_to_ship'
                        && $shipment->status === 'unassigned'
                        && !$sorting,
                    422,
                    'Seller pickup requires a ready-to-ship parcel that has not been received at a hub.'
                );
            } else {
                abort_unless(
                    in_array(
                        $shipment->status,
                        [
                            'unassigned',
                            'picked_up',
                            'in_transit',
                        ],
                        true
                    )
                        && $sorting
                        && (int) $sorting->logistics_provider_id
                            === $providerId
                        && $sorting->stage === 'sorted'
                        && $sorting->waybill_verified_at,
                    422,
                    'Hub transfer requires a verified, sorted parcel at your hub.'
                );
            }

            $assignments->record(
                $shipment,
                $rider,
                $data['job_type'],
                isset($data['destination_provider_id'])
                    ? (int) $data['destination_provider_id']
                    : null,
                $data['note'] ?? null
            );
        }, 3);

        return redirect()
            ->route('logistics.tasks.index', [
                'job_type' => $data['job_type'],
            ])
            ->with(
                'success',
                'Task assigned to a rider with the matching job type.'
            );
    }
}