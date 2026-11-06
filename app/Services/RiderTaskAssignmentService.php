<?php

namespace App\Services;

use App\Support\RiderJobType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderTaskAssignmentService
{
    public const TERMINAL_STATUSES = [
        'completed',
        'delivered',
        'cancelled',
    ];

    public function record(
        object $shipment,
        object $rider,
        string $jobType,
        ?int $destinationProviderId = null,
        ?string $note = null
    ): int {
        if (DB::connection()->transactionLevel() < 1) {
            throw new \LogicException(
                'Task assignment requires a database transaction.'
            );
        }

        $awaitingReceipt = DB::table('logistics_hub_transfers')
            ->where('shipment_id', $shipment->id)
            ->where('status', 'in_transit')
            ->exists();

        if ($awaitingReceipt) {
            throw ValidationException::withMessages([
                'shipment_ids' =>
                    'The destination hub must receive this parcel before another task can be assigned.',
            ]);
        }

        RiderJobType::ensureMatches($rider, $jobType);

        if (
            (int) $shipment->logistics_provider_id
                !== (int) $rider->logistics_provider_id
        ) {
            throw ValidationException::withMessages([
                'rider_id' =>
                    'Rider and shipment must belong to the same logistics provider.',
            ]);
        }

        if (!RiderJobType::isAvailable($rider)) {
            throw ValidationException::withMessages([
                'rider_id' => 'Rider is not available.',
            ]);
        }

        if ($jobType === RiderJobType::HUB_TRANSFER) {
            $destination = DB::table('logistics_providers')
                ->where('id', $destinationProviderId)
                ->where('status', 'approved')
                ->first();

            if (
                !$destination
                || (int) $destination->id
                    === (int) $shipment->logistics_provider_id
            ) {
                throw ValidationException::withMessages([
                    'destination_provider_id' =>
                        'Choose a different approved destination hub.',
                ]);
            }
        } elseif ($destinationProviderId !== null) {
            throw ValidationException::withMessages([
                'destination_provider_id' =>
                    'A destination hub is only used for hub-to-hub tasks.',
            ]);
        }

        $activeTasks = DB::table('rider_tasks')
            ->where('shipment_id', $shipment->id)
            ->where(function ($query) {
                $query
                    ->whereNotIn(
                        'status',
                        self::TERMINAL_STATUSES
                    )
                    ->orWhereNull('status');
            })
            ->lockForUpdate()
            ->get();

        if ($activeTasks->isNotEmpty()) {
            $task = $activeTasks->first();

            if (
                $activeTasks->count() !== 1
                || $task->job_type !== $jobType
                || (int) $task->logistics_provider_id
                    !== (int) $shipment->logistics_provider_id
                || $task->status !== 'assigned'
            ) {
                throw ValidationException::withMessages([
                    'shipment_ids' =>
                        'This parcel already has an active task. Complete it before assigning the next leg.',
                ]);
            }

            if (
                $jobType !== RiderJobType::DELIVERY
                && (
                    (int) $task->rider_id !== (int) $rider->id
                    || $task->destination_provider_id
                        != $destinationProviderId
                )
            ) {
                throw ValidationException::withMessages([
                    'shipment_ids' =>
                        'This parcel already has a different assignment.',
                ]);
            }

            DB::table('rider_tasks')
                ->where('id', $task->id)
                ->update([
                    'rider_id' => $rider->id,
                    'destination_provider_id' => $destinationProviderId,
                    'note' => $note,
                    'updated_at' => now(),
                ]);

            return (int) $task->id;
        }

        return DB::table('rider_tasks')->insertGetId([
            'shipment_id' => $shipment->id,
            'rider_id' => $rider->id,
            'logistics_provider_id' => $shipment->logistics_provider_id,
            'destination_provider_id' => $destinationProviderId,
            'job_type' => $jobType,
            'status' => 'assigned',
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}