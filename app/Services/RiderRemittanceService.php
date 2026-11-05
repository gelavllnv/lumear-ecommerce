<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RiderRemittanceService
{
    public const REMITTANCE_HOURS = 24;

    private function now(): Carbon
    {
        return Carbon::now('UTC');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'remittance' => $message,
        ]);
    }

    private function verifyRider(
        int $riderId,
        int $providerId
    ): object {
        $rider = DB::table('riders')
            ->where('id', $riderId)
            ->where('logistics_provider_id', $providerId)
            ->first();

        if (!$rider) {
            $this->fail(
                'Rider does not belong to this logistics provider.'
            );
        }

        return $rider;
    }

    private function audit(
        int $riderId,
        int $providerId,
        string $action,
        ?int $performedBy = null,
        ?int $remittanceId = null,
        ?int $obligationId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $remarks = null
    ): void {
        $now = $this->now();

        DB::table('rider_remittance_audit_logs')->insert([
            'rider_id' => $riderId,
            'logistics_provider_id' => $providerId,
            'rider_remittance_id' => $remittanceId,
            'rider_cod_obligation_id' => $obligationId,
            'performed_by' => $performedBy,
            'action' => $action,
            'old_values' => $oldValues === null
                ? null
                : json_encode($oldValues),
            'new_values' => $newValues === null
                ? null
                : json_encode($newValues),
            'remarks' => $remarks,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE COD OBLIGATION
    |--------------------------------------------------------------------------
    |
    | Creates one obligation for a delivered shipment
    | whose COD collection has been confirmed.
    |
    | Does not create or credit any wallet money.
    |
    */

    public function createCodObligation(
        int $shipmentId,
        ?Carbon $collectedAt = null
    ): bool {
        return DB::transaction(function () use (
            $shipmentId,
            $collectedAt
        ) {
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->lockForUpdate()
                ->first();

            if (!$shipment) {
                $this->fail('Shipment not found.');
            }

            if (
                $shipment->status !== 'delivered'
                || !(bool) $shipment->cod_collected
                || !$shipment->rider_id
                || !$shipment->logistics_provider_id
                || (int) $shipment->cod_amount_minor <= 0
            ) {
                return false;
            }

            $riderId = (int) $shipment->rider_id;
            $providerId = (int) $shipment->logistics_provider_id;

            $this->verifyRider($riderId, $providerId);

            if (
                DB::table('rider_cod_obligations')
                    ->where('shipment_id', $shipmentId)
                    ->exists()
            ) {
                return false;
            }

            $now = $this->now();

            $collectionTime = $collectedAt
                ? $collectedAt->copy()->utc()
                : $now->copy();

            if ($collectionTime->gt($now)) {
                $this->fail(
                    'COD collection time cannot be in the future.'
                );
            }

            $dueAt = $collectionTime->copy()
                ->addHours(self::REMITTANCE_HOURS);

            $obligationId = DB::table(
                'rider_cod_obligations'
            )->insertGetId([
                'rider_id' => $riderId,
                'logistics_provider_id' => $providerId,
                'shipment_id' => $shipmentId,
                'expected_amount_minor' =>
                    (int) $shipment->cod_amount_minor,
                'settled_amount_minor' => 0,
                'status' => 'pending',
                'collected_at' => $collectionTime,
                'due_at' => $dueAt,
                'settled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit(
                $riderId,
                $providerId,
                'cod_obligation_created',
                null,
                null,
                $obligationId,
                null,
                [
                    'shipment_id' => $shipmentId,
                    'expected_amount_minor' =>
                        (int) $shipment->cod_amount_minor,
                    'collected_at' =>
                        $collectionTime->toDateTimeString(),
                    'due_at' => $dueAt->toDateTimeString(),
                ]
            );

            return true;
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | RIDER COD BALANCE
    |--------------------------------------------------------------------------
    */

    public function getRiderBalance(
        int $riderId,
        int $providerId
    ): array {
        $this->verifyRider($riderId, $providerId);

        $obligations = DB::table('rider_cod_obligations')
            ->where('rider_id', $riderId)
            ->where('logistics_provider_id', $providerId)
            ->where('status', '!=', 'cancelled')
            ->get();

        $expected = 0;
        $settled = 0;
        $overdue = 0;
        $now = $this->now();

        foreach ($obligations as $obligation) {
            $expected += (int) $obligation->expected_amount_minor;
            $settled += (int) $obligation->settled_amount_minor;

            $remaining = max(
                0,
                (int) $obligation->expected_amount_minor
                - (int) $obligation->settled_amount_minor
            );

            if (
                $remaining > 0
                && Carbon::parse($obligation->due_at, 'UTC')->lt($now)
            ) {
                $overdue += $remaining;
            }
        }

        return [
            'expected_minor' => $expected,
            'settled_minor' => $settled,
            'outstanding_minor' => max(
                0,
                $expected - $settled
            ),
            'overdue_minor' => $overdue,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT REMITTANCE FOR VERIFICATION
    |--------------------------------------------------------------------------
    |
    | This is a pending payment claim, NOT a completed
    | wallet-to-wallet transfer.
    |
    | Do not use this method to manufacture rider wallet
    | balances or mark unreceived funds as settled.
    |
    */

    public function submitDigitalRemittance(
        int $riderId,
        int $providerId,
        string $paymentMethod,
        string $transactionReference,
        int $amountMinor,
        ?string $receiptPath = null,
        ?string $paymentProvider = null,
        ?string $transferredAt = null
    ): int {
        $this->verifyRider($riderId, $providerId);

        // Existing database enum supports these values.
        // A real internal wallet transfer will require
        // a separate wallet ledger and migration.
        if (!in_array(
            $paymentMethod,
            ['gcash', 'maya', 'bank_transfer', 'other'],
            true
        )) {
            $this->fail('Invalid payment method.');
        }

        $transactionReference = trim($transactionReference);

        if (
            $transactionReference === ''
            || mb_strlen($transactionReference) > 150
        ) {
            $this->fail('Invalid transaction reference.');
        }

        if ($amountMinor <= 0) {
            $this->fail(
                'Remittance amount must be greater than zero.'
            );
        }

        if (
            $paymentProvider !== null
            && mb_strlen($paymentProvider) > 100
        ) {
            $this->fail('Payment provider name is too long.');
        }

        if (
            $receiptPath !== null
            && (
                mb_strlen($receiptPath) > 500
                || str_contains($receiptPath, '..')
                || str_starts_with($receiptPath, '/')
                || str_contains($receiptPath, '\\')
            )
        ) {
            $this->fail('Invalid receipt storage path.');
        }

        $transferTime = null;

        if ($transferredAt !== null) {
            try {
                $transferTime = Carbon::parse(
                    $transferredAt,
                    'Asia/Manila'
                )->utc();
            } catch (\Throwable $e) {
                $this->fail('Invalid transfer date and time.');
            }

            if (
                $transferTime->gt(
                    $this->now()->copy()->addMinutes(5)
                )
            ) {
                $this->fail(
                    'Transfer time cannot be in the future.'
                );
            }
        }

        return DB::transaction(function () use (
            $riderId,
            $providerId,
            $paymentMethod,
            $transactionReference,
            $amountMinor,
            $receiptPath,
            $paymentProvider,
            $transferTime
        ) {
            $duplicate = DB::table('rider_remittances')
                ->where('logistics_provider_id', $providerId)
                ->where('payment_method', $paymentMethod)
                ->where(
                    'transaction_reference',
                    $transactionReference
                )
                ->exists();

            if ($duplicate) {
                $this->fail(
                    'This transaction reference has already been submitted.'
                );
            }

            $now = $this->now();

            $id = DB::table('rider_remittances')
                ->insertGetId([
                    'rider_id' => $riderId,
                    'logistics_provider_id' => $providerId,
                    'remittance_number' =>
                        'REM-' . strtoupper((string) Str::ulid()),
                    'payment_method' => $paymentMethod,
                    'payment_provider' => $paymentProvider,
                    'transaction_reference' =>
                        $transactionReference,
                    'submitted_amount_minor' => $amountMinor,
                    'receipt_path' => $receiptPath,
                    'status' => 'pending_verification',
                    'transferred_at' => $transferTime,
                    'submitted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $this->audit(
                $riderId,
                $providerId,
                'digital_remittance_submitted',
                null,
                $id,
                null,
                null,
                [
                    'amount_minor' => $amountMinor,
                    'payment_method' => $paymentMethod,
                    'status' => 'pending_verification',
                ]
            );

            return $id;
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY REMITTANCE AND RECORD COD HOLDING LEDGER
    |--------------------------------------------------------------------------
    |
    | Logistics must independently verify actual receipt.
    |
    | Allocations and the COD ledger entry are written
    | within the same database transaction.
    |
    */

    public function verifyDigitalRemittance(
        int $remittanceId,
        int $providerId,
        int $verifiedBy,
        ?string $remarks = null
    ): array {
        return DB::transaction(function () use (
            $remittanceId,
            $providerId,
            $verifiedBy,
            $remarks
        ) {
            $remittance = DB::table('rider_remittances')
                ->where('id', $remittanceId)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$remittance) {
                $this->fail('Remittance not found.');
            }

            if (
                $remittance->status !== 'pending_verification'
            ) {
                $this->fail(
                    'Only pending remittances can be verified.'
                );
            }

            $this->verifyRider(
                (int) $remittance->rider_id,
                $providerId
            );

            $remaining = (int) $remittance->submitted_amount_minor;
            $allocated = 0;
            $now = $this->now();

            $obligations = DB::table('rider_cod_obligations')
                ->where('rider_id', $remittance->rider_id)
                ->where('logistics_provider_id', $providerId)
                ->whereNotIn('status', [
                    'settled',
                    'cancelled',
                    'disputed',
                ])
                ->orderBy('due_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($obligations as $obligation) {
                if ($remaining <= 0) {
                    break;
                }

                $outstanding = max(
                    0,
                    (int) $obligation->expected_amount_minor
                    - (int) $obligation->settled_amount_minor
                );

                if ($outstanding <= 0) {
                    continue;
                }

                $amount = min($remaining, $outstanding);

                DB::table(
                    'rider_remittance_allocations'
                )->insert([
                    'rider_remittance_id' => $remittanceId,
                    'rider_cod_obligation_id' => $obligation->id,
                    'amount_minor' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $newSettled =
                    (int) $obligation->settled_amount_minor
                    + $amount;

                $fullySettled = $newSettled >=
                    (int) $obligation->expected_amount_minor;

                DB::table('rider_cod_obligations')
                    ->where('id', $obligation->id)
                    ->update([
                        'settled_amount_minor' => $newSettled,
                        'status' => $fullySettled
                            ? 'settled'
                            : 'partially_settled',
                        'settled_at' => $fullySettled
                            ? $now
                            : null,
                        'updated_at' => $now,
                    ]);

                $this->audit(
                    (int) $remittance->rider_id,
                    $providerId,
                    'cod_obligation_payment_allocated',
                    $verifiedBy,
                    $remittanceId,
                    (int) $obligation->id,
                    [
                        'settled_amount_minor' =>
                            (int) $obligation->settled_amount_minor,
                    ],
                    [
                        'allocated_amount_minor' => $amount,
                        'settled_amount_minor' => $newSettled,
                    ]
                );

                $remaining -= $amount;
                $allocated += $amount;
            }

            DB::table('rider_remittances')
                ->where('id', $remittanceId)
                ->update([
                    'status' => 'verified',
                    'verified_by' => $verifiedBy,
                    'verified_at' => $now,
                    'verification_remarks' => $remarks,
                    'updated_at' => $now,
                ]);

            /*
            | Record verified funds separately from
            | Logistics shipping commission earnings.
            */

            app(LogisticsCodWalletService::class)
                ->recordVerifiedRemittance(
                    $remittanceId,
                    $providerId
                );

            /*
            | Verified overpayments require reconciliation.
            | Do not credit them to commission earnings.
            */

            if ($remaining > 0) {
                DB::table(
                    'rider_remittance_discrepancies'
                )->insert([
                    'rider_id' => $remittance->rider_id,
                    'logistics_provider_id' => $providerId,
                    'rider_remittance_id' => $remittanceId,
                    'type' => 'overage',
                    'expected_amount_minor' => $allocated,
                    'actual_amount_minor' =>
                        (int) $remittance->submitted_amount_minor,
                    'difference_minor' => $remaining,
                    'status' => 'open',
                    'description' =>
                        'Verified funds exceed eligible COD obligations. Reconciliation required.',
                    'reported_by' => $verifiedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->audit(
                (int) $remittance->rider_id,
                $providerId,
                'digital_remittance_verified',
                $verifiedBy,
                $remittanceId,
                null,
                ['status' => 'pending_verification'],
                [
                    'status' => 'verified',
                    'allocated_minor' => $allocated,
                    'unallocated_minor' => $remaining,
                    'cod_ledger_recorded' => true,
                ],
                $remarks
            );

            $this->syncOverdueRestriction(
                (int) $remittance->rider_id,
                $providerId
            );

            return [
                'verified' => true,
                'allocated_minor' => $allocated,
                'unallocated_minor' => $remaining,
            ];
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT REMITTANCE
    |--------------------------------------------------------------------------
    */

    public function rejectDigitalRemittance(
        int $remittanceId,
        int $providerId,
        int $reviewedBy,
        string $reason
    ): void {
        $reason = trim($reason);

        if ($reason === '') {
            $this->fail('A rejection reason is required.');
        }

        DB::transaction(function () use (
            $remittanceId,
            $providerId,
            $reviewedBy,
            $reason
        ) {
            $remittance = DB::table('rider_remittances')
                ->where('id', $remittanceId)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$remittance) {
                $this->fail('Remittance not found.');
            }

            if (
                $remittance->status !== 'pending_verification'
            ) {
                $this->fail(
                    'Only pending remittances can be rejected.'
                );
            }

            $now = $this->now();

            DB::table('rider_remittances')
                ->where('id', $remittanceId)
                ->update([
                    'status' => 'rejected',
                    'verified_by' => $reviewedBy,
                    'verified_at' => $now,
                    'rejection_reason' => $reason,
                    'updated_at' => $now,
                ]);

            $this->audit(
                (int) $remittance->rider_id,
                $providerId,
                'digital_remittance_rejected',
                $reviewedBy,
                $remittanceId,
                null,
                ['status' => 'pending_verification'],
                ['status' => 'rejected'],
                $reason
            );

            $this->syncOverdueRestriction(
                (int) $remittance->rider_id,
                $providerId
            );
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | SYNCHRONIZE OVERDUE RESTRICTIONS
    |--------------------------------------------------------------------------
    |
    | Never modifies riders.is_active.
    | Never deducts money from Rider Payroll.
    |
    */

    public function syncOverdueRestriction(
        int $riderId,
        int $providerId
    ): bool {
        return DB::transaction(function () use (
            $riderId,
            $providerId
        ) {
            $rider = DB::table('riders')
                ->where('id', $riderId)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$rider) {
                $this->fail('Rider not found.');
            }

            $now = $this->now();

            $overdue = DB::table('rider_cod_obligations')
                ->where('rider_id', $riderId)
                ->where('logistics_provider_id', $providerId)
                ->whereNotIn('status', [
                    'settled',
                    'cancelled',
                ])
                ->where('due_at', '<', $now)
                ->whereRaw(
                    'expected_amount_minor > settled_amount_minor'
                )
                ->exists();

            $activeRestrictions = DB::table(
                'rider_remittance_restrictions'
            )
                ->where('rider_id', $riderId)
                ->where('logistics_provider_id', $providerId)
                ->where('restriction_type', 'overdue_remittance')
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($overdue && $activeRestrictions->isEmpty()) {
                $id = DB::table(
                    'rider_remittance_restrictions'
                )->insertGetId([
                    'rider_id' => $riderId,
                    'logistics_provider_id' => $providerId,
                    'restriction_type' => 'overdue_remittance',
                    'status' => 'active',
                    'blocks_new_assignments' => true,
                    'blocks_pickup_scanning' => true,
                    'reason' =>
                        'COD remittance deadline exceeded.',
                    'restricted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->audit(
                    $riderId,
                    $providerId,
                    'remittance_restriction_applied',
                    null,
                    null,
                    null,
                    null,
                    ['restriction_id' => $id]
                );
            }

            if (!$overdue && $activeRestrictions->isNotEmpty()) {
                DB::table('rider_remittance_restrictions')
                    ->whereIn(
                        'id',
                        $activeRestrictions->pluck('id')->all()
                    )
                    ->update([
                        'status' => 'lifted',
                        'lifted_at' => $now,
                        'lift_reason' =>
                            'Overdue COD obligations cleared.',
                        'updated_at' => $now,
                    ]);

                $this->audit(
                    $riderId,
                    $providerId,
                    'remittance_restriction_lifted',
                    null,
                    null,
                    null,
                    null,
                    [
                        'reason' =>
                            'Overdue COD obligations cleared.',
                    ]
                );
            }

            return $overdue;
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | RIDER OPERATION PERMISSIONS
    |--------------------------------------------------------------------------
    */

    public function canAcceptNewAssignment(
        int $riderId,
        int $providerId
    ): bool {
        $rider = $this->verifyRider(
            $riderId,
            $providerId
        );

        if (
            !(bool) $rider->is_active
            || $rider->management_status !== 'approved'
        ) {
            return false;
        }

        $this->syncOverdueRestriction(
            $riderId,
            $providerId
        );

        return !DB::table('rider_remittance_restrictions')
            ->where('rider_id', $riderId)
            ->where('logistics_provider_id', $providerId)
            ->where('status', 'active')
            ->where('blocks_new_assignments', true)
            ->exists();
    }

    public function canScanNewPickup(
        int $riderId,
        int $providerId
    ): bool {
        $rider = $this->verifyRider(
            $riderId,
            $providerId
        );

        if (
            !(bool) $rider->is_active
            || $rider->management_status !== 'approved'
        ) {
            return false;
        }

        $this->syncOverdueRestriction(
            $riderId,
            $providerId
        );

        return !DB::table('rider_remittance_restrictions')
            ->where('rider_id', $riderId)
            ->where('logistics_provider_id', $providerId)
            ->where('status', 'active')
            ->where('blocks_pickup_scanning', true)
            ->exists();
    }
}
