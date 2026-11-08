<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\OrderTimeline;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Services\RiderRemittanceService;
use App\Services\RiderTaskAssignmentService;
use App\Support\RiderHubEligibility;
use App\Support\RiderJobType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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

        $hub = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();

        abort_unless($hub, 403, 'Approved logistics hub required.');

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
                    && RiderHubEligibility::matches($rider, $hub)
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
            ->join(
                'logistics_providers as origin',
                'origin.id',
                '=',
                'rt.logistics_provider_id'
            )
            ->where('rt.logistics_provider_id', $providerId)
            ->where('rt.job_type', $jobType)
            ->whereNull('rt.return_request_id')
            ->select(
                'rt.*',
                's.tracking_code',
                'u.name as rider_name',
                'destination.name as destination_name',
                'origin.name as origin_name'
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

    public function returns(): View
    {
        $providerId = $this->providerId();
        $hub = DB::table('logistics_providers')->where('id', $providerId)->first();
        abort_unless($hub, 403, 'Approved logistics hub required.');

        $riders = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->where('r.job_type', RiderJobType::DELIVERY)
            ->select('r.*', 'u.name as rider_name')
            ->orderBy('u.name')
            ->get()
            ->filter(fn ($rider): bool => RiderHubEligibility::matches($rider, $hub)
                && RiderJobType::isAvailable($rider))
            ->values();
        $handoverRiders = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->where('r.job_type', RiderJobType::HUB_TRANSFER)
            ->select('r.*', 'u.name as rider_name')
            ->orderBy('u.name')
            ->get()
            ->filter(fn ($rider): bool => RiderHubEligibility::matches($rider, $hub)
                && RiderJobType::isAvailable($rider))
            ->values();

        $waitingReturns = DB::table('return_requests as rr')
            ->join('seller_orders as so', 'so.id', '=', 'rr.seller_order_id')
            ->join('shipments as s', 's.seller_order_id', '=', 'so.id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->join('order_items as oi', 'oi.id', '=', 'rr.order_item_id')
            ->join('users as buyer', 'buyer.id', '=', 'rr.buyer_id')
            ->where('rr.status', 'approved')
            ->whereIn('rr.pickup_status', ['not_started', 'awaiting_assignment', 'pickup_failed'])
            ->where('so.logistics_provider_id', $providerId)
            ->where('s.logistics_provider_id', $providerId)
            ->where('s.status', 'delivered')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('rider_tasks as rt')
                    ->whereColumn('rt.return_request_id', 'rr.id')
                    ->whereNotIn('rt.status', ['failed', 'cancelled']);
            })
            ->select('rr.id', 'rr.reason', 'rr.description', 'rr.pickup_status', 'oi.product_name', 'o.reference', 'o.shipping_address', 'buyer.name as buyer_name', 'buyer.phone as buyer_phone')
            ->latest('rr.id')
            ->get()
            ->map(function (object $returnRequest): object {
                $address = json_decode((string) $returnRequest->shipping_address, true);
                $returnRequest->pickup_address = is_array($address)
                    ? collect(['line1', 'barangay', 'city', 'province'])->map(fn (string $key): string => (string) ($address[$key] ?? ''))->filter()->implode(', ')
                    : '';

                return $returnRequest;
            });

        $pickupTasks = DB::table('rider_tasks as rt')
            ->join('return_requests as rr', 'rr.id', '=', 'rt.return_request_id')
            ->join('seller_orders as so', 'so.id', '=', 'rr.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->join('order_items as oi', 'oi.id', '=', 'rr.order_item_id')
            ->join('riders as r', 'r.id', '=', 'rt.rider_id')
            ->join('users as rider', 'rider.id', '=', 'r.user_id')
            ->where('rt.logistics_provider_id', $providerId)
            ->select('rt.*', 'rr.reason', 'rr.description', 'rr.pickup_status', 'rr.delivery_status', 'oi.product_name', 'o.reference', 'rider.name as rider_name')
            ->latest('rt.id')
            ->paginate(20, ['*'], 'pickup_tasks_page')
            ->withQueryString();

        $returnDeliveries = DB::table('return_requests as rr')
            ->join('seller_orders as so', 'so.id', '=', 'rr.seller_order_id')
            ->join('shipments as s', 's.seller_order_id', '=', 'so.id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->join('order_items as oi', 'oi.id', '=', 'rr.order_item_id')
            ->join('sellers as seller', 'seller.id', '=', 'so.seller_id')
            ->leftJoin('addresses as seller_address', 'seller_address.id', '=', 'seller.pickup_address_id')
            ->join('users as seller_owner', 'seller_owner.id', '=', 'seller.user_id')
            ->leftJoin('logistics_providers as destination', 'destination.id', '=', 'rr.return_destination_provider_id')
            ->where('rr.status', 'approved')
            ->where('rr.return_current_provider_id', $providerId)
            ->whereIn('rr.delivery_status', ['awaiting_assignment', 'at_destination_hub', 'delivery_failed'])
            ->select(
                'rr.id', 'rr.reason', 'rr.description', 'rr.delivery_status',
                'rr.return_destination_provider_id', 'oi.product_name', 'o.reference',
                's.id as shipment_id', 's.tracking_code', 'seller.name as seller_name',
                'seller_owner.phone as seller_phone', 'seller_address.line1 as seller_address',
                'seller_address.city as seller_city', 'seller_address.province as seller_province',
                'destination.name as destination_hub'
            )
            ->latest('rr.id')
            ->get();

        $hubs = DB::table('logistics_providers')
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'municipality_name', 'province_name']);

        return view('logistics.returns.index', compact(
            'riders',
            'handoverRiders',
            'waitingReturns',
            'pickupTasks',
            'returnDeliveries',
            'hubs',
            'providerId',
        ));
    }

    public function store(
        Request $request,
        RiderTaskAssignmentService $assignments,
        RiderRemittanceService $remittances
    ) {
        $providerId = $this->providerId();
        $hub = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();

        abort_unless($hub, 403, 'Approved logistics hub required.');

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
            $hub,
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

            RiderHubEligibility::ensureMatches($rider, $hub);

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

    public function storeReturnPickup(Request $request, RiderRemittanceService $remittances): RedirectResponse
    {
        $providerId = $this->providerId();
        $data = $request->validate([
            'return_request_id' => ['required', 'integer', 'min:1'],
            'rider_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($providerId, $data, $remittances): void {
            $returnRequest = ReturnRequest::query()
                ->with('sellerOrder')
                ->lockForUpdate()
                ->findOrFail($data['return_request_id']);
            abort_unless(
                $returnRequest->status === 'approved'
                    && in_array($returnRequest->pickup_status, ['not_started', 'awaiting_assignment', 'pickup_failed'], true),
                422,
                'Only approved returns waiting for pickup can be assigned.'
            );

            $shipment = DB::table('shipments')
                ->where('seller_order_id', $returnRequest->seller_order_id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();
            abort_unless($shipment && $shipment->status === 'delivered', 422, 'The delivered parcel is not assigned to this hub.');
            abort_unless((int) $returnRequest->sellerOrder->logistics_provider_id === $providerId, 403);

            $hub = DB::table('logistics_providers')->where('id', $providerId)->first();
            $rider = DB::table('riders')
                ->where('id', $data['rider_id'])
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();
            abort_unless($hub && $rider, 422, 'Choose a delivery rider registered with this hub.');
            RiderHubEligibility::ensureMatches($rider, $hub);
            RiderJobType::ensureMatches($rider, RiderJobType::DELIVERY);
            abort_unless($remittances->canAcceptNewAssignment((int) $rider->id, $providerId), 422, 'Choose an available rider who can receive assignments.');
            $existingTask = DB::table('rider_tasks')
                ->where('return_request_id', $returnRequest->id)
                ->lockForUpdate()
                ->first();
            if ($existingTask) {
                abort_unless(in_array($existingTask->status, ['failed', 'cancelled'], true), 422, 'This return already has an active rider pickup task.');
                DB::table('rider_tasks')->where('id', $existingTask->id)->update([
                    'rider_id' => $rider->id,
                    'return_leg' => 'pickup',
                    'status' => 'assigned',
                    'note' => $data['note'] ?? null,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('rider_tasks')->insert([
                    'shipment_id' => $shipment->id,
                    'return_request_id' => $returnRequest->id,
                    'return_leg' => 'pickup',
                    'rider_id' => $rider->id,
                    'logistics_provider_id' => $providerId,
                    'destination_provider_id' => $providerId,
                    'job_type' => RiderJobType::DELIVERY,
                    'status' => 'assigned',
                    'note' => $data['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $returnRequest->update(['pickup_status' => 'assigned']);
        }, 3);

        return redirect()->route('logistics.returns.index')
            ->with('success', 'Return pickup assigned to the delivery rider.');
    }

    public function receiveReturnPickup(int $taskId): RedirectResponse
    {
        $providerId = $this->providerId();

        DB::transaction(function () use ($taskId, $providerId): void {
            $task = DB::table('rider_tasks')
                ->where('id', $taskId)
                ->where('logistics_provider_id', $providerId)
                ->whereNotNull('return_request_id')
                ->lockForUpdate()
                ->first();
            abort_unless($task && $task->status === 'collected', 422, 'The rider must confirm pickup before hub receipt.');

            $returnRequest = ReturnRequest::query()->lockForUpdate()->findOrFail($task->return_request_id);
            abort_unless($returnRequest->status === 'approved' && $returnRequest->pickup_status === 'collected', 422, 'This return is no longer awaiting hub receipt.');

            DB::table('rider_tasks')->where('id', $task->id)->update(['status' => 'completed', 'updated_at' => now()]);
            $returnRequest->update([
                'pickup_status' => 'received',
                'delivery_status' => 'awaiting_assignment',
                'return_current_provider_id' => $providerId,
                'return_destination_provider_id' => null,
            ]);
            DB::table('delivery_events')->insert([
                'shipment_id' => $task->shipment_id,
                'status' => 'return_'.$returnRequest->id.'_received',
                'attempt' => 1,
                'user_id' => Auth::id(),
                'note' => 'Returned item received at the logistics hub.',
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('logistics.returns.index')
            ->with('success', 'Returned item received at the hub.');
    }

    public function storeReturnDelivery(Request $request, RiderRemittanceService $remittances): RedirectResponse
    {
        $providerId = $this->providerId();
        $data = $request->validate([
            'return_request_id' => ['required', 'integer', 'min:1'],
            'destination_provider_id' => ['required', 'integer', 'min:1'],
            'rider_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($providerId, $data, $remittances): void {
            $returnRequest = ReturnRequest::query()
                ->lockForUpdate()
                ->findOrFail($data['return_request_id']);

            abort_unless(
                $returnRequest->status === 'approved'
                    && $returnRequest->pickup_status === 'received'
                    && (int) $returnRequest->return_current_provider_id === $providerId
                    && in_array($returnRequest->delivery_status, ['awaiting_assignment', 'at_destination_hub', 'delivery_failed'], true),
                422,
                'This return is not ready for a delivery assignment.'
            );

            $destinationId = (int) $data['destination_provider_id'];
            $destination = DB::table('logistics_providers')
                ->where('id', $destinationId)
                ->where('status', 'approved')
                ->first();
            abort_unless($destination, 422, 'Choose an approved destination hub.');

            if ($returnRequest->delivery_status === 'at_destination_hub') {
                abort_unless(
                    $destinationId === $providerId,
                    422,
                    'This return has arrived. Assign a local delivery rider to the seller.'
                );
            }

            $jobType = $destinationId === $providerId
                ? RiderJobType::DELIVERY
                : RiderJobType::HUB_TRANSFER;
            $hub = DB::table('logistics_providers')->where('id', $providerId)->first();
            $rider = DB::table('riders')
                ->where('id', $data['rider_id'])
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();
            abort_unless($hub && $rider, 422, 'Choose a rider registered with this hub.');
            RiderHubEligibility::ensureMatches($rider, $hub);
            RiderJobType::ensureMatches($rider, $jobType);
            abort_unless(
                $remittances->canAcceptNewAssignment((int) $rider->id, $providerId),
                422,
                'Choose an available rider who can receive assignments.'
            );

            $shipment = DB::table('shipments')
                ->where('seller_order_id', $returnRequest->seller_order_id)
                ->first();
            abort_unless($shipment, 404, 'The original shipment record was not found.');

            $task = DB::table('rider_tasks')
                ->where('return_request_id', $returnRequest->id)
                ->lockForUpdate()
                ->first();
            abort_unless($task, 422, 'The return pickup task is missing.');
            abort_unless(in_array($task->status, ['completed', 'delivered', 'failed'], true), 422, 'The previous return leg must be completed first.');

            DB::table('rider_tasks')->where('id', $task->id)->update([
                'shipment_id' => $shipment->id,
                'rider_id' => $rider->id,
                'logistics_provider_id' => $providerId,
                'destination_provider_id' => $destinationId,
                'job_type' => $jobType,
                'return_leg' => $jobType === RiderJobType::HUB_TRANSFER
                    ? 'hub_transfer'
                    : 'seller_delivery',
                'status' => 'assigned',
                'note' => $data['note'] ?? null,
                'updated_at' => now(),
            ]);

            $returnRequest->update([
                'return_destination_provider_id' => $destinationId,
                'delivery_status' => $jobType === RiderJobType::HUB_TRANSFER
                    ? 'truck_assigned'
                    : 'delivery_assigned',
            ]);
        }, 3);

        return redirect()->route('logistics.returns.index')
            ->with('success', 'Return delivery task assigned.');
    }

    public function receiveSellerPickup(int $taskId): RedirectResponse
    {
        $providerId = $this->providerId();

        DB::transaction(function () use ($taskId, $providerId): void {
            $task = DB::table('rider_tasks')
                ->where('id', $taskId)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $task
                    && $task->job_type === RiderJobType::SELLER_PICKUP
                    && $task->status === 'seller_confirmed',
                422,
                'Only a seller-confirmed pickup can be received at this hub.'
            );

            $shipment = DB::table('shipments')
                ->where('id', $task->shipment_id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $shipment && $shipment->status === 'unassigned',
                422,
                'This parcel is no longer awaiting pickup handover.'
            );

            $sellerOrder = SellerOrder::query()
                ->where('id', $shipment->seller_order_id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                DB::table('logistics_sorting')
                    ->where('shipment_id', $shipment->id)
                    ->exists(),
                422,
                'This parcel has already been received at a hub.'
            );

            $now = now();

            DB::table('rider_tasks')
                ->where('id', $task->id)
                ->update([
                    'status' => 'completed',
                    'updated_at' => $now,
                ]);

            DB::table('shipments')
                ->where('id', $shipment->id)
                ->update([
                    'status' => 'picked_up',
                    'rider_id' => null,
                    'updated_at' => $now,
                ]);

            DB::table('logistics_sorting')->insert([
                'shipment_id' => $shipment->id,
                'logistics_provider_id' => $providerId,
                'stage' => 'received',
                'received_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('delivery_events')->insert([
                'shipment_id' => $shipment->id,
                'status' => 'sorting_received',
                'attempt' => max(1, (int) ($shipment->attempts ?? 0)),
                'user_id' => Auth::id(),
                'note' => 'Seller pickup received at the logistics hub.',
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            OrderTimeline::create([
                'order_id' => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'actor_id' => Auth::id(),
                'status' => $sellerOrder->status,
                'event' => 'logistics_hub_received',
                'note' => 'Seller pickup received at the logistics hub.',
                'metadata' => [
                    'shipment_id' => $shipment->id,
                    'tracking_code' => $shipment->tracking_code,
                    'logistics_provider_id' => $providerId,
                ],
            ]);
        }, 3);

        return redirect()
            ->route('logistics.tasks.index', [
                'job_type' => RiderJobType::SELLER_PICKUP,
            ])
            ->with(
                'success',
                'Parcel received at your hub. It is now available in Incoming Parcels and Parcel Sorting.'
            );
    }
}
