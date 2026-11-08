<?php

namespace App\Services;

use App\Support\RiderJobType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HubHandoverConfirmationService
{
    public function __construct(private HubTransferService $transfers) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'transfer' => $message,
        ]);
    }

    public function confirm(
        int $taskId,
        int $providerId,
        int $userId,
        string $trackingCode,
        ?string $condition = null,
        ?string $remarks = null
    ): bool {
        abort_unless(
            DB::table('logistics_providers')
                ->where('id', $providerId)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->exists(),
            403
        );

        $locator = DB::table('rider_tasks')
            ->where('id', $taskId)
            ->where('destination_provider_id', $providerId)
            ->first();

        abort_unless($locator, 404);

        return DB::transaction(function () use (
            $locator,
            $taskId,
            $providerId,
            $userId,
            $trackingCode,
            $condition,
            $remarks
        ) {
            $driver = DB::table('riders')
                ->where('id', $locator->rider_id)
                ->lockForUpdate()
                ->first();

            $shipment = DB::table('shipments')
                ->where('id', $locator->shipment_id)
                ->lockForUpdate()
                ->first();

            $task = DB::table('rider_tasks')
                ->where('id', $taskId)
                ->where('destination_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($driver && $shipment && $task, 404);

            if (
                (int) $task->shipment_id !== (int) $shipment->id
                || (int) $task->rider_id !== (int) $driver->id
            ) {
                $this->fail('The task changed. Refresh the page.');
            }

            $existing = DB::table('logistics_hub_transfers')
                ->where('rider_task_id', $taskId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->transfers->receive(
                    (int) $existing->id,
                    $providerId,
                    $userId,
                    $trackingCode,
                    $condition,
                    $remarks
                );
            }

            $sourceId = (int) $task->logistics_provider_id;

            if (
                $task->job_type !== RiderJobType::HUB_TRANSFER
                || (int) $driver->logistics_provider_id !== $sourceId
                || $driver->job_type !== RiderJobType::HUB_TRANSFER
                || $sourceId === $providerId
                || strtolower(trim((string) $task->status)) === 'cancelled'
            ) {
                $this->fail(
                    'This task is not eligible for destination-hub confirmation.'
                );
            }

            if (
                ! hash_equals(
                    (string) $shipment->tracking_code,
                    trim($trackingCode)
                )
            ) {
                $this->fail(
                    'The entered waybill does not match this parcel.'
                );
            }

            if (
                (int) $shipment->logistics_provider_id !== $sourceId
                || ! in_array(
                    $shipment->status,
                    ['unassigned', 'assigned', 'picked_up', 'in_transit'],
                    true
                )
                || (
                    $shipment->rider_id
                    && (int) $shipment->rider_id !== (int) $driver->id
                )
            ) {
                $this->fail(
                    'The parcel is no longer held by the origin hub or its assigned driver.'
                );
            }

            if (
                DB::table('logistics_hub_transfers')
                    ->where('shipment_id', $shipment->id)
                    ->where('status', 'in_transit')
                    ->lockForUpdate()
                    ->first()
            ) {
                $this->fail(
                    'Another transfer is already awaiting receipt for this parcel.'
                );
            }

            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $shipment->id)
                ->lockForUpdate()
                ->first();

            if (
                ! $sorting
                || (int) $sorting->logistics_provider_id !== $sourceId
                || $sorting->stage !== 'sorted'
                || ! $sorting->waybill_verified_at
            ) {
                $this->fail(
                    'The origin hub must have received, verified, and sorted this parcel.'
                );
            }

            $transferId = DB::table('logistics_hub_transfers')
                ->insertGetId([
                    'rider_task_id' => $taskId,
                    'shipment_id' => $shipment->id,
                    'rider_id' => $driver->id,
                    'source_provider_id' => $sourceId,
                    'destination_provider_id' => $providerId,
                    'status' => 'in_transit',
                    'sorting_snapshot' => json_encode(
                        (array) $sorting,
                        JSON_THROW_ON_ERROR
                    ),
                    'dispatched_by' => null,
                    'dispatched_at' => null,
                    'origin_dispatch_confirmed' => false,
                    'registered_by' => $userId,
                    'registered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            // Registration and receipt commit together.
            // Unknown origin departure details remain empty.
            DB::table('shipments')
                ->where('id', $shipment->id)
                ->update([
                    'rider_id' => null,
                    'status' => 'in_transit',
                    'updated_at' => now(),
                ]);

            DB::table('delivery_events')->insert([
                'shipment_id' => $shipment->id,
                'status' => 'hub_handover_registered',
                'attempt' => max(1, (int) ($shipment->attempts ?? 0)),
                'user_id' => $userId,
                'note' => 'Destination hub registered physical handover for task #'
                    .$taskId
                    .'. Origin departure details were not registered.',
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Reuse the existing receipt checks and custody updates.
            return $this->transfers->receive(
                $transferId,
                $providerId,
                $userId,
                $trackingCode,
                $condition,
                $remarks
            );
        }, 3);
    }
}
