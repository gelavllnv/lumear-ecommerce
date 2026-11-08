<?php

namespace App\Services;

use App\Support\LogisticsNotification;
use App\Support\RiderJobType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HubTransferService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'transfer' => $message,
        ]);
    }

    private function authorize(int $providerId, int $userId): void
    {
        $allowed = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->exists();

        abort_unless($allowed, 403);
    }

    private function event(
        object $shipment,
        int $userId,
        string $status,
        string $note
    ): void {
        DB::table('delivery_events')->insert([
            'shipment_id' => $shipment->id,
            'status' => $status,
            'attempt' => max(
                1,
                (int) ($shipment->attempts ?? 0)
            ),
            'user_id' => $userId,
            'note' => $note,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function dispatch(
        int $taskId,
        int $providerId,
        int $userId
    ): bool {
        $this->authorize($providerId, $userId);

        $locator = DB::table('rider_tasks')
            ->where('id', $taskId)
            ->where('logistics_provider_id', $providerId)
            ->first();

        abort_unless($locator, 404);

        return DB::transaction(function () use (
            $locator,
            $taskId,
            $providerId,
            $userId
        ) {
            $rider = DB::table('riders')
                ->where('id', $locator->rider_id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            $shipment = DB::table('shipments')
                ->where('id', $locator->shipment_id)
                ->lockForUpdate()
                ->first();

            $task = DB::table('rider_tasks')
                ->where('id', $taskId)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment && $task && $rider, 404);
            $isReturnTransfer = $task->return_request_id !== null
                && $task->return_leg === 'hub_transfer';

            if (
                (int) $task->shipment_id !== (int) $shipment->id
                || (int) $task->rider_id !== (int) $rider->id
            ) {
                $this->fail(
                    'Assignment changed. Refresh this page.'
                );
            }

            $existing = DB::table('logistics_hub_transfers')
                ->where('rider_task_id', $taskId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return false;
            }

            $dispatchableStatuses = [
                'assigned',
                'heading',
                'in_transit',
                'arrived',
            ];

            if (
                $task->job_type !== RiderJobType::HUB_TRANSFER
                || !in_array($task->status, $dispatchableStatuses, true)
            ) {
                $this->fail(
                    'Only an assigned Long-distance Handover task can be dispatched.'
                );
            }

            RiderJobType::ensureMatches(
                $rider,
                RiderJobType::HUB_TRANSFER
            );

            if (
                $task->status === 'assigned'
                && !RiderJobType::isAvailable($rider)
            ) {
                $this->fail(
                    'The assigned driver is unavailable or suspended.'
                );
            }

            $destinationId = (int) $task->destination_provider_id;

            $destinationApproved = DB::table('logistics_providers')
                ->where('id', $destinationId)
                ->where('status', 'approved')
                ->exists();

            if (
                $destinationId === $providerId
                || !$destinationApproved
            ) {
                $this->fail(
                    'Select a different approved destination hub.'
                );
            }

            if (!$isReturnTransfer && (
                (int) $shipment->logistics_provider_id !== $providerId
                || $shipment->rider_id
                || !in_array(
                    $shipment->status,
                    ['unassigned', 'picked_up', 'in_transit'],
                    true
                )
            )) {
                $this->fail(
                    'This parcel is no longer available for hub transfer.'
                );
            }

            if ($isReturnTransfer) {
                $returnRequest = DB::table('return_requests')
                    ->where('id', $task->return_request_id)
                    ->lockForUpdate()
                    ->first();
                if (
                    !$returnRequest
                    || (int) $returnRequest->return_current_provider_id !== $providerId
                    || (int) $returnRequest->return_destination_provider_id !== $destinationId
                    || $returnRequest->delivery_status !== 'truck_assigned'
                ) {
                    $this->fail('This return is no longer assigned for this hub transfer.');
                }
            }

            $unreceivedTransfer = DB::table('logistics_hub_transfers')
                ->where('shipment_id', $shipment->id)
                ->where('status', 'in_transit')
                ->exists();

            if ($unreceivedTransfer) {
                $this->fail(
                    'This parcel already has an unreceived transfer.'
                );
            }

            $this->noOtherTask(
                (int) $shipment->id,
                $taskId
            );

            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $shipment->id)
                ->lockForUpdate()
                ->first();

            if (!$isReturnTransfer && (
                !$sorting
                || (int) $sorting->logistics_provider_id !== $providerId
                || $sorting->stage !== 'sorted'
                || !$sorting->waybill_verified_at
            )) {
                $this->fail(
                    'Receive, verify, and sort the parcel before dispatching.'
                );
            }

            $order = DB::table('seller_orders')
                ->where('id', $shipment->seller_order_id)
                ->lockForUpdate()
                ->first();

            if (!$isReturnTransfer && (
                !$order
                || (int) $order->logistics_provider_id !== $providerId
                || in_array(
                    $order->status,
                    ['delivered', 'cancelled'],
                    true
                )
            )) {
                $this->fail(
                    'The seller order is unavailable for transfer.'
                );
            }

            if (!$isReturnTransfer) {
                $this->singleShipment($shipment);
            }

            DB::table('logistics_hub_transfers')->insert([
                'rider_task_id' => $taskId,
                'shipment_id' => $shipment->id,
                'rider_id' => $rider->id,
                'source_provider_id' => $providerId,
                'destination_provider_id' => $destinationId,
                'status' => 'in_transit',
                'sorting_snapshot' => json_encode(
                    (array) ($sorting ?? []),
                    JSON_THROW_ON_ERROR
                ),
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!in_array($task->status, ['in_transit', 'arrived'], true)) {
                DB::table('rider_tasks')
                    ->where('id', $taskId)
                    ->update([
                        'status' => 'in_transit',
                        'updated_at' => now(),
                    ]);
            }

            if (!$isReturnTransfer) {
                DB::table('shipments')
                    ->where('id', $shipment->id)
                    ->update([
                        'status' => 'in_transit',
                        'updated_at' => now(),
                    ]);
            }

            $this->event(
                $shipment,
                $userId,
                $isReturnTransfer
                    ? 'return_' . $task->return_request_id . '_hub_transfer_dispatched'
                    : 'hub_transfer_dispatched',
                $isReturnTransfer
                    ? "Return parcel dispatched from hub #{$providerId} to hub #{$destinationId}; task #{$taskId}."
                    : "Dispatched from hub #{$providerId} to hub #{$destinationId}; task #{$taskId}."
            );

            LogisticsNotification::create(
                $destinationId,
                'hub_transfer_incoming',
                'Incoming Hub Transfer',
                'Waybill ' . $shipment->tracking_code
                    . ' is on its way.',
                '/logistics/hub-transfers'
            );

            return true;
        }, 3);
    }

    public function receive(
        int $transferId,
        int $providerId,
        int $userId,
        string $trackingCode,
        ?string $condition = null,
        ?string $remarks = null
    ): bool {
        $this->authorize($providerId, $userId);

        $locator = DB::table('logistics_hub_transfers')
            ->where('id', $transferId)
            ->where('destination_provider_id', $providerId)
            ->first();

        abort_unless($locator, 404);

        return DB::transaction(function () use (
            $locator,
            $transferId,
            $providerId,
            $userId,
            $trackingCode,
            $condition,
            $remarks
        ) {
            $shipment = DB::table('shipments')
                ->where('id', $locator->shipment_id)
                ->lockForUpdate()
                ->first();

            $task = DB::table('rider_tasks')
                ->where('id', $locator->rider_task_id)
                ->lockForUpdate()
                ->first();

            $transfer = DB::table('logistics_hub_transfers')
                ->where('id', $transferId)
                ->where('destination_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment && $task && $transfer, 404);
            $isReturnTransfer = $task->return_request_id !== null
                && $task->return_leg === 'hub_transfer';

            if (
                !hash_equals(
                    (string) $shipment->tracking_code,
                    trim($trackingCode)
                )
            ) {
                $this->fail(
                    'The entered waybill does not match this parcel.'
                );
            }

            // Repeated receipt must not move custody again.
            if ($transfer->status === 'received') {
                return false;
            }

            $sourceId = (int) $transfer->source_provider_id;

            if (
                $transfer->status !== 'in_transit'
                || (!$isReturnTransfer && (
                    (int) $shipment->logistics_provider_id !== $sourceId
                    || $shipment->status !== 'in_transit'
                    || $shipment->rider_id
                ))
                || (int) $task->shipment_id !== (int) $shipment->id
                || (int) $task->rider_id !== (int) $transfer->rider_id
                || (int) $task->logistics_provider_id !== $sourceId
                || (int) $task->destination_provider_id !== $providerId
                || $task->job_type !== RiderJobType::HUB_TRANSFER
            ) {
                $this->fail(
                    'Transfer records changed or this parcel is not awaiting receipt.'
                );
            }

            if ($isReturnTransfer) {
                $returnRequest = DB::table('return_requests')
                    ->where('id', $task->return_request_id)
                    ->lockForUpdate()
                    ->first();
                if (
                    !$returnRequest
                    || (int) $returnRequest->return_current_provider_id !== $sourceId
                    || (int) $returnRequest->return_destination_provider_id !== $providerId
                    || $returnRequest->delivery_status !== 'truck_assigned'
                ) {
                    $this->fail('Return handover details changed or are no longer awaiting receipt.');
                }
            }

            $this->noOtherTask(
                (int) $shipment->id,
                (int) $task->id
            );

            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $shipment->id)
                ->lockForUpdate()
                ->first();

            $order = DB::table('seller_orders')
                ->where('id', $shipment->seller_order_id)
                ->lockForUpdate()
                ->first();

            if (!$isReturnTransfer && (
                !$sorting
                || (int) $sorting->logistics_provider_id !== $sourceId
                || !$order
                || (int) $order->logistics_provider_id !== $sourceId
                || in_array(
                    $order->status,
                    ['delivered', 'cancelled'],
                    true
                )
            )) {
                $this->fail(
                    'Source hub records no longer match this transfer.'
                );
            }

            if (!$isReturnTransfer) {
                $this->singleShipment($shipment);
            }

            if ($isReturnTransfer) {
                DB::table('return_requests')
                    ->where('id', $task->return_request_id)
                    ->update([
                        'return_current_provider_id' => $providerId,
                        'delivery_status' => 'at_destination_hub',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('shipments')
                    ->where('id', $shipment->id)
                    ->update([
                        'logistics_provider_id' => $providerId,
                        'rider_id' => null,
                        'status' => 'in_transit',
                        'updated_at' => now(),
                    ]);

                DB::table('seller_orders')
                    ->where('id', $order->id)
                    ->update([
                        'logistics_provider_id' => $providerId,
                        'updated_at' => now(),
                    ]);

                // Keep one current sorting record per shipment.
                // The origin sorting record is preserved in sorting_snapshot.
                DB::table('logistics_sorting')
                    ->where('id', $sorting->id)
                    ->update([
                        'logistics_provider_id' => $providerId,
                        'stage' => 'received',
                        'batch_id' => null,
                        'received_at' => now(),
                        'sorted_at' => null,
                        'waybill_verified_at' => null,
                        'waybill_verified_by' => null,
                        'waybill_verification_method' => null,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('rider_tasks')
                ->where('id', $task->id)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);

            DB::table('logistics_hub_transfers')
                ->where('id', $transferId)
                ->update([
                    'status' => 'received',
                    'received_by' => $userId,
                    'received_at' => now(),
                    'condition' => $condition,
                    'receipt_remarks' => $remarks,
                    'updated_at' => now(),
                ]);

            $this->event(
                $shipment,
                $userId,
                $isReturnTransfer
                    ? 'return_' . $task->return_request_id . '_hub_transfer_received'
                    : 'hub_transfer_received',
                trim(
                    ($isReturnTransfer ? 'Return parcel at ' : 'Hub #')
                    . "#{$providerId} received transfer #{$transferId}. {$condition} {$remarks}"
                )
            );

            LogisticsNotification::create(
                $sourceId,
                'hub_transfer_received',
                'Hub Transfer Received',
                'Waybill ' . $shipment->tracking_code
                    . ' was received by the destination hub.',
                '/logistics/hub-transfers'
            );

            LogisticsNotification::create(
                $providerId,
                'hub_transfer_received',
                $isReturnTransfer ? 'Return Parcel Received' : 'Transferred Parcel Received',
                $isReturnTransfer
                    ? 'Assign a local rider to deliver the return to the seller.'
                    : 'Verify and sort waybill ' . $shipment->tracking_code . ' at your hub.',
                $isReturnTransfer ? '/logistics/returns' : '/logistics/scanner'
            );

            return true;
        }, 3);
    }

    private function noOtherTask(
        int $shipmentId,
        int $taskId
    ): void {
        $exists = DB::table('rider_tasks')
            ->where('shipment_id', $shipmentId)
            ->where('id', '!=', $taskId)
            ->where(function ($query) {
                $query
                    ->whereNotIn(
                        'status',
                        RiderTaskAssignmentService::TERMINAL_STATUSES
                    )
                    ->orWhereNull('status');
            })
            ->exists();

        if ($exists) {
            $this->fail(
                'Another active task must be resolved before this transfer can proceed.'
            );
        }
    }

    private function singleShipment(object $shipment): void
    {
        $hasSibling = DB::table('shipments')
            ->where('seller_order_id', $shipment->seller_order_id)
            ->where('id', '!=', $shipment->id)
            ->exists();

        if ($hasSibling) {
            $this->fail(
                'This seller order has multiple shipments and requires a split-shipment transfer workflow.'
            );
        }
    }
}
