<?php

namespace App\Http\Controllers;

use App\Models\OrderTimeline;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Services\RiderRemittanceService;
use App\Services\RiderTaskAssignmentService;
use App\Support\RiderHubEligibility;
use App\Support\RiderJobType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RiderTaskController extends Controller
{
    public function page(Request $request): \Illuminate\View\View
    {
        $logistics = $request->user()->hasRole('logistics');

        return view('logistics.tasks', ['layout' => $logistics ? 'layouts.logistics' : 'layouts.seller', 'taskBase' => $logistics ? '/logistics/tasks' : '/seller/pickup-tasks', 'logistics' => $logistics]);
    }

    public function index(Request $request): JsonResponse
    {
        $rider = $request->attributes->get('rider_api_rider');
        $query = DB::table('rider_tasks as t')
            ->join('shipments as s', 's.id', '=', 't.shipment_id')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->join('sellers as seller', 'seller.id', '=', 'so.seller_id')
            ->join('users as owner', 'owner.id', '=', 'seller.user_id')
            ->leftJoin('addresses as pickup', 'pickup.id', '=', 'seller.pickup_address_id')
            ->join('logistics_providers as origin', 'origin.id', '=', 't.logistics_provider_id')
            ->leftJoin('logistics_providers as destination', 'destination.id', '=', 't.destination_provider_id')
            ->leftJoin('return_requests as rr', 'rr.id', '=', 't.return_request_id')
            ->leftJoin('order_items as return_item', 'return_item.id', '=', 'rr.order_item_id')
            ->leftJoin('users as return_buyer', 'return_buyer.id', '=', 'rr.buyer_id')
            ->select('t.*', 's.tracking_code', 'o.reference', 'o.shipping_address', 'seller.name as seller_name', 'owner.phone as seller_phone', 'pickup.line1 as pickup_address', 'pickup.city as pickup_city', 'pickup.province as pickup_province', 'origin.name as origin', 'origin.municipality_name as origin_municipality', 'origin.province_name as origin_province', 'destination.name as destination', 'destination.municipality_name as destination_municipality', 'destination.province_name as destination_province', 'rr.reason as return_reason', 'rr.description as return_description', 'rr.pickup_status as return_pickup_status', 'rr.delivery_status as return_delivery_status', 'rr.return_destination_provider_id', 'return_item.product_name as return_product_name', 'return_buyer.name as return_buyer_name', 'return_buyer.phone as return_buyer_phone');
        if ($rider) {
            $query->where('t.rider_id', $rider->id)->where('t.job_type', $rider->job_type);
        } elseif ($request->user()->hasRole('logistics')) {
            $providerId = $request->user()->logisticsProvider->id;
            $query->where(fn ($q) => $q->where('t.logistics_provider_id', $providerId)->orWhere('t.destination_provider_id', $providerId));
        } else {
            $query->where('seller.user_id', $request->user()->id);
        }

        return response()->json(['tasks' => $query->orderByDesc('t.id')->get()]);
    }

    public function assign(
        Request $request,
        RiderTaskAssignmentService $assignments,
        RiderRemittanceService $remittances
    ): JsonResponse
    {
        $data = $request->validate([
            'rider_id' => ['required', 'integer', 'min:1'],
            'shipment_id' => ['required', 'integer', 'min:1'],
            'job_type' => ['required', Rule::in([RiderJobType::SELLER_PICKUP, RiderJobType::HUB_TRANSFER])],
            'destination_provider_id' => [Rule::requiredIf($request->input('job_type') === RiderJobType::HUB_TRANSFER), 'nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider?->status === 'approved', 403);

        $id = DB::transaction(function () use ($data, $provider, $assignments, $remittances): int {
            $shipment = DB::table('shipments')->where('id', $data['shipment_id'])->where('logistics_provider_id', $provider->id)->lockForUpdate()->first();
            abort_unless($shipment, 404);
            $rider = DB::table('riders')->where('id', $data['rider_id'])->where('logistics_provider_id', $provider->id)->lockForUpdate()->first();
            abort_unless($rider, 422, 'Choose a driver registered with your hub.');
            RiderHubEligibility::ensureMatches($rider, $provider);
            RiderJobType::ensureMatches($rider, $data['job_type']);
            abort_unless($remittances->canAcceptNewAssignment((int) $rider->id, (int) $provider->id), 422, 'Choose an available driver who can receive assignments.');

            if ($data['job_type'] === 'Seller Pickup') {
                abort_unless($remittances->canScanNewPickup((int) $rider->id, (int) $provider->id), 422, 'This driver is restricted from seller pickups.');
                $sellerOrder = DB::table('seller_orders')->where('id', $shipment->seller_order_id)->where('logistics_provider_id', $provider->id)->first();
                abort_unless($sellerOrder && $sellerOrder->status === 'ready_to_ship' && $shipment->status === 'unassigned', 422, 'Seller pickup requires a ready-to-ship parcel.');
                abort_if(DB::table('logistics_sorting')->where('shipment_id', $shipment->id)->exists(), 422, 'This parcel is already at a logistics hub.');

                abort_if(DB::table('rider_tasks')->where('shipment_id', $shipment->id)->whereNotIn('status', ['completed', 'delivered', 'cancelled', 'failed'])->exists(), 422, 'This parcel already has an active task.');

                return DB::table('rider_tasks')->insertGetId([
                    'shipment_id' => $shipment->id,
                    'rider_id' => $rider->id,
                    'logistics_provider_id' => $provider->id,
                    'destination_provider_id' => $provider->id,
                    'job_type' => RiderJobType::SELLER_PICKUP,
                    'status' => 'assigned',
                    'note' => $data['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                abort_unless(in_array($shipment->status, ['unassigned', 'picked_up', 'in_transit'], true) && ! $shipment->rider_id, 422, 'Only a parcel held by this hub can be transferred.');
                $sorting = DB::table('logistics_sorting')->where('shipment_id', $shipment->id)->where('logistics_provider_id', $provider->id)->first();
                abort_unless($sorting && $sorting->stage === 'sorted' && $sorting->waybill_verified_at, 422, 'Hub transfer requires a waybill-verified, sorted parcel at your hub.');
                abort_unless((int) $data['destination_provider_id'] !== (int) $provider->id && DB::table('logistics_providers')->where('id', $data['destination_provider_id'])->where('status', 'approved')->exists(), 422, 'Choose another approved destination hub.');

                return $assignments->record(
                    $shipment,
                    $rider,
                    RiderJobType::HUB_TRANSFER,
                    (int) $data['destination_provider_id'],
                    $data['note'] ?? null
                );
            }
        });

        return response()->json(['id' => $id, 'success' => true], 201);
    }

    public function update(Request $request, int $taskId): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['heading', 'arrived', 'collected', 'seller_confirmed', 'in_transit', 'completed', 'delivered', 'failed'])], 'note' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $taskId, $data): void {
            $task = DB::table('rider_tasks')->where('id', $taskId)->lockForUpdate()->first();
            abort_unless($task, 404);
            $shipment = DB::table('shipments')->where('id', $task->shipment_id)->lockForUpdate()->first();
            $sellerOrder = SellerOrder::with('seller')->lockForUpdate()->findOrFail($shipment->seller_order_id);
            $rider = $request->attributes->get('rider_api_rider');
            $next = $data['status'];
            $returnLeg = $task->return_leg ?? ($task->return_request_id !== null ? 'pickup' : null);
            $isReturnPickup = $task->return_request_id !== null && $returnLeg === 'pickup';
            $isReturnSellerDelivery = $task->return_request_id !== null && $returnLeg === 'seller_delivery';
            $isReturnTask = $task->return_request_id !== null;
            if ($rider) {
                abort_unless((int) $task->rider_id === (int) $rider->id && $task->job_type === $rider->job_type, 404);
                if ($isReturnPickup || $isReturnSellerDelivery) {
                    abort_unless($task->job_type === RiderJobType::DELIVERY, 403);
                } else {
                    abort_if(in_array($next, ['seller_confirmed', 'completed'], true), 403, 'The seller or receiving logistics hub must confirm this handover.');
                }
            } elseif ($isReturnPickup) {
                abort_unless($next === 'completed' && (int) $task->logistics_provider_id === (int) $request->user()->logisticsProvider?->id, 403);
                abort_unless($task->status === 'collected', 422, 'The rider must confirm pickup before the hub can receive this return.');
            } elseif ($next === 'seller_confirmed') {
                abort_unless($task->job_type === 'Seller Pickup' && $sellerOrder->seller->user_id === $request->user()->id, 403);
            } else {
                abort_unless($next === 'completed' && $request->user()->logisticsProvider?->id === (int) $task->destination_provider_id, 403);
            }
            if ($task->status === $next) {
                return;
            }
            $allowed = $isReturnPickup
                ? ['assigned' => ['heading', 'failed'], 'heading' => ['arrived', 'failed'], 'arrived' => ['collected', 'failed'], 'collected' => $rider ? [] : ['completed']]
                : ($isReturnSellerDelivery
                    ? ['assigned' => ['heading', 'failed'], 'heading' => ['arrived', 'failed'], 'arrived' => $rider ? ['delivered', 'failed'] : []]
                : ($task->job_type === 'Seller Pickup'
                ? ['assigned' => ['heading', 'failed'], 'heading' => ['arrived', 'failed'], 'arrived' => ['collected', 'failed'], 'collected' => ['seller_confirmed'], 'seller_confirmed' => ['completed']]
                : ['assigned' => ['heading', 'failed'], 'heading' => ['in_transit', 'failed'], 'in_transit' => ['arrived', 'failed'], 'arrived' => ['completed']]));
            abort_unless(in_array($next, $allowed[$task->status] ?? [], true), 422, 'Invalid task transition. Refresh and use the next available action.');
            abort_if(! $isReturnTask && in_array($sellerOrder->status, ['completed', 'cancelled'], true), 422, 'This order is already closed.');
            DB::table('rider_tasks')->where('id', $taskId)->update(['status' => $next, 'note' => $data['note'] ?? null, 'updated_at' => now()]);
            if ($isReturnPickup && $next === 'collected') {
                ReturnRequest::query()->whereKey($task->return_request_id)->update(['pickup_status' => 'collected']);
            }
            if ($isReturnPickup && $next === 'completed') {
                ReturnRequest::query()->whereKey($task->return_request_id)->update(['pickup_status' => 'received']);
            }
            if ($isReturnPickup && $next === 'failed') {
                ReturnRequest::query()->whereKey($task->return_request_id)->update(['pickup_status' => 'pickup_failed']);
            }
            if ($isReturnSellerDelivery && $next === 'delivered') {
                ReturnRequest::query()->whereKey($task->return_request_id)->update(['delivery_status' => 'seller_received']);
            }
            if ($isReturnSellerDelivery && $next === 'failed') {
                ReturnRequest::query()->whereKey($task->return_request_id)->update(['delivery_status' => 'delivery_failed']);
            }
            if ($isReturnTask && $returnLeg === 'hub_transfer' && $next === 'failed') {
                ReturnRequest::query()->whereKey($task->return_request_id)->update([
                    'delivery_status' => 'awaiting_assignment',
                    'return_destination_provider_id' => null,
                ]);
            }
            if ($next === 'seller_confirmed') {
                $sellerOrder->update(['status' => 'shipped']);
            }
            if ($next === 'completed' && ! $isReturnTask) {
                DB::table('shipments')->where('id', $shipment->id)->update(['logistics_provider_id' => $task->destination_provider_id, 'rider_id' => null, 'status' => 'picked_up', 'updated_at' => now()]);
                $sellerOrder->update(['logistics_provider_id' => $task->destination_provider_id, 'status' => 'shipped']);
                DB::table('logistics_sorting')->updateOrInsert(['shipment_id' => $shipment->id], ['logistics_provider_id' => $task->destination_provider_id, 'stage' => 'received', 'received_at' => now(), 'sorted_at' => null, 'updated_at' => now(), 'created_at' => now()]);
            }
            $actorId = $rider?->user_id ?? $request->user()->id;
            $event = $isReturnTask ? 'return_'.$task->return_request_id.'_'.$returnLeg.'_'.$next : 'task_'.$taskId.'_'.$next;
            $description = $isReturnTask ? 'Return '.str_replace('_', ' ', $returnLeg).': '.str_replace('_', ' ', $next).'.' : $task->job_type.': '.str_replace('_', ' ', $next).'.';
            $attempt = ((int) DB::table('delivery_events')->where('shipment_id', $shipment->id)->where('status', $event)->max('attempt')) + 1;
            DB::table('delivery_events')->insert(['shipment_id' => $shipment->id, 'status' => $event, 'attempt' => $attempt, 'user_id' => $actorId, 'note' => $description.' '.($data['note'] ?? ''), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            OrderTimeline::create(['order_id' => $sellerOrder->order_id, 'seller_order_id' => $sellerOrder->id, 'actor_id' => $actorId, 'status' => $sellerOrder->status, 'event' => $event, 'note' => $description]);
        });

        return response()->json(['success' => true]);
    }

}
