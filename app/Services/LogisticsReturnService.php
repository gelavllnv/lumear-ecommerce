<?php

namespace App\Services;

use App\Support\RegisteredRiderArea;
use App\Support\RiderJobType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsReturnService
{
    private function fail(
        string $field,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    private function event(
        object $case,
        string $status,
        ?int $userId = null,
        ?int $riderId = null,
        ?string $note = null,
        array $details = []
    ): void {
        DB::table('logistics_return_events')->insert([
            'return_case_id' => $case->id,
            'logistics_provider_id' => $case->logistics_provider_id,
            'rider_id' => $riderId,
            'user_id' => $userId,
            'status' => $status,
            'note' => $note,
            'details' => $details
                ? json_encode($details, JSON_THROW_ON_ERROR)
                : null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN RETURN INTAKE
    |--------------------------------------------------------------------------
    |
    | The Admin feature calls this only after approving
    | a PHYSICAL return. Do not call it for direct refunds.
    |
    */

    public function acceptApprovedReturn(array $data): int
    {
        foreach ([
            'return_reference',
            'shipment_id',
            'buyer_name',
            'buyer_phone',
            'pickup_address',
            'return_reason',
            'seller_return_destination',
        ] as $key) {
            if (
                ! array_key_exists($key, $data)
                || $data[$key] === ''
                || $data[$key] === null
            ) {
                $this->fail(
                    $key,
                    'This approved physical return requires '
                    .$key
                    .'.'
                );
            }
        }

        if (
            ! is_array($data['pickup_address'])
            || ! RegisteredRiderArea::hasArea(
                $data['pickup_address']
            )
        ) {
            $this->fail(
                'pickup_address',
                'Pickup address must include municipality/city and province.'
            );
        }

        return DB::transaction(function () use ($data) {
            $shipment = DB::table('shipments')
                ->where('id', $data['shipment_id'])
                ->lockForUpdate()
                ->first();

            abort_unless(
                $shipment,
                404,
                'Delivered shipment not found.'
            );

            $order = DB::table('seller_orders')
                ->where('id', $shipment->seller_order_id)
                ->lockForUpdate()
                ->first();

            if (
                ! $order
                || strtolower((string) $shipment->status)
                    !== 'delivered'
            ) {
                $this->fail(
                    'shipment_id',
                    'Only a delivered parcel can enter physical return logistics.'
                );
            }

            $provider = DB::table('logistics_providers')
                ->where('id', $shipment->logistics_provider_id)
                ->where('status', 'approved')
                ->first();

            if (
                ! $provider
                || (int) $order->logistics_provider_id
                    !== (int) $provider->id
            ) {
                $this->fail(
                    'shipment_id',
                    'The delivered parcel has no approved current logistics provider.'
                );
            }

            $id = DB::table('logistics_return_cases')
                ->insertGetId([
                    'return_reference' => trim($data['return_reference']),

                    'shipment_id' => $shipment->id,

                    'seller_order_id' => $order->id,

                    'logistics_provider_id' => $provider->id,

                    'buyer_name' => trim($data['buyer_name']),

                    'buyer_phone' => trim($data['buyer_phone']),

                    'pickup_address' => json_encode(
                        $data['pickup_address'],
                        JSON_THROW_ON_ERROR
                    ),

                    'return_reason' => trim($data['return_reason']),

                    'seller_return_destination' => trim($data['seller_return_destination']),

                    'approved_at' => $data['approved_at'] ?? now(),

                    'pickup_deadline' => $data['pickup_deadline'] ?? null,

                    'status' => 'approved',

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $case = DB::table('logistics_return_cases')
                ->where('id', $id)
                ->first();

            $this->event(
                $case,
                'approved',
                $data['approved_by'] ?? null,
                null,
                'Admin approved physical return.'
            );

            return $id;
        }, 3);
    }

    public function schedule(
        int $caseId,
        int $providerId,
        int $userId,
        int $riderId,
        string $date,
        string $start,
        string $end,
        ?string $note = null
    ): void {
        DB::transaction(function () use (
            $caseId,
            $providerId,
            $userId,
            $riderId,
            $date,
            $start,
            $end,
            $note
        ) {
            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($case, 404);

            if (
                ! in_array(
                    $case->status,
                    [
                        'approved',
                        'pickup_failed',
                        'scheduled',
                    ],
                    true
                )
            ) {
                $this->fail(
                    'return',
                    'This return can no longer be scheduled.'
                );
            }

            if ($end <= $start) {
                $this->fail(
                    'schedule_end',
                    'Pickup window end must be after the start time.'
                );
            }

            $rider = DB::table('riders')
                ->where('id', $riderId)
                ->where(
                    'logistics_provider_id',
                    $providerId
                )
                ->lockForUpdate()
                ->first();

            if (
                ! $rider
                || $rider->job_type !== RiderJobType::SELLER_PICKUP
                || ! RiderJobType::isAvailable($rider)
            ) {
                $this->fail(
                    'rider_id',
                    'Choose an available Seller Pickup rider.'
                );
            }

            if (
                ! RegisteredRiderArea::matches(
                    $rider,
                    $case->pickup_address
                )
            ) {
                $this->fail(
                    'rider_id',
                    'This rider is outside the buyer pickup municipality and province.'
                );
            }

            $conflict = DB::table('logistics_return_cases')
                ->where('rider_id', $riderId)
                ->where('scheduled_date', $date)
                ->whereIn('status', [
                    'scheduled',
                    'en_route',
                ])
                ->where('id', '!=', $caseId)
                ->where('schedule_start', '<', $end)
                ->where('schedule_end', '>', $start)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                $this->fail(
                    'rider_id',
                    'This rider already has a return pickup during that time window.'
                );
            }

            DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->update([
                    'rider_id' => $riderId,
                    'scheduled_date' => $date,
                    'schedule_start' => $start,
                    'schedule_end' => $end,
                    'scheduled_by' => $userId,
                    'scheduled_at' => now(),
                    'status' => 'scheduled',
                    'updated_at' => now(),
                ]);

            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->first();

            $this->event(
                $case,
                'return_pickup_scheduled',
                $userId,
                $riderId,
                $note,
                compact('date', 'start', 'end')
            );
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | RIDER-APP PICKUP
    |--------------------------------------------------------------------------
    |
    | Your rider-app teammate calls this after the rider
    | scans the return reference and physically gets the parcel.
    |
    */

    public function markPickedUp(
        int $caseId,
        int $riderId,
        string $reference,
        ?string $condition = null,
        ?string $remarks = null
    ): void {
        DB::transaction(function () use (
            $caseId,
            $riderId,
            $reference,
            $condition,
            $remarks
        ) {
            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->where('rider_id', $riderId)
                ->lockForUpdate()
                ->first();

            abort_unless($case, 404);

            if ($case->status !== 'scheduled') {
                $this->fail(
                    'return',
                    'This return is not awaiting pickup.'
                );
            }

            if (
                ! hash_equals(
                    $case->return_reference,
                    trim($reference)
                )
            ) {
                $this->fail(
                    'return_reference',
                    'Return reference does not match.'
                );
            }

            DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->update([
                    'status' => 'picked_up',
                    'picked_up_at' => now(),
                    'pickup_condition' => $condition,
                    'pickup_remarks' => $remarks,
                    'updated_at' => now(),
                ]);

            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->first();

            $this->event(
                $case,
                'return_picked_up',
                null,
                $riderId,
                $remarks,
                [
                    'condition' => $condition,
                ]
            );
        }, 3);
    }

    public function receiveAtHub(
        int $caseId,
        int $providerId,
        int $userId,
        string $reference,
        ?string $condition = null,
        ?string $remarks = null
    ): void {
        DB::transaction(function () use (
            $caseId,
            $providerId,
            $userId,
            $reference,
            $condition,
            $remarks
        ) {
            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->where(
                    'logistics_provider_id',
                    $providerId
                )
                ->lockForUpdate()
                ->first();

            abort_unless($case, 404);

            if ($case->status !== 'picked_up') {
                $this->fail(
                    'return',
                    'Only a rider-collected return can be received at the hub.'
                );
            }

            if (
                ! hash_equals(
                    $case->return_reference,
                    trim($reference)
                )
            ) {
                $this->fail(
                    'return_reference',
                    'Return reference does not match.'
                );
            }

            DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->update([
                    'status' => 'received_at_hub',
                    'hub_received_by' => $userId,
                    'hub_received_at' => now(),
                    'hub_condition' => $condition,
                    'hub_remarks' => $remarks,
                    'updated_at' => now(),
                ]);

            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->first();

            $this->event(
                $case,
                'return_received_at_hub',
                $userId,
                null,
                $remarks,
                [
                    'condition' => $condition,
                ]
            );
        }, 3);
    }

    public function handToSeller(
        int $caseId,
        int $providerId,
        int $userId,
        string $recipient,
        ?string $remarks = null
    ): void {
        DB::transaction(function () use (
            $caseId,
            $providerId,
            $userId,
            $recipient,
            $remarks
        ) {
            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->where(
                    'logistics_provider_id',
                    $providerId
                )
                ->lockForUpdate()
                ->first();

            abort_unless($case, 404);

            if ($case->status !== 'received_at_hub') {
                $this->fail(
                    'return',
                    'The return must be received at your hub before seller handoff.'
                );
            }

            DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->update([
                    'status' => 'handed_to_seller',
                    'seller_handed_by' => $userId,
                    'seller_handed_at' => now(),
                    'seller_recipient_name' => trim($recipient),
                    'seller_handoff_remarks' => $remarks,
                    'updated_at' => now(),
                ]);

            $case = DB::table('logistics_return_cases')
                ->where('id', $caseId)
                ->first();

            $this->event(
                $case,
                'return_handed_to_seller',
                $userId,
                null,
                $remarks,
                [
                    'recipient' => trim($recipient),
                ]
            );
        }, 3);
    }
}
