<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsCodWalletService
{
    /**
     * Record an already-verified remittance in the COD ledger.
     *
     * Must be called inside the same database transaction
     * that verifies and allocates the remittance.
     *
     * Returns true if a new ledger entry was created.
     */
    public function recordVerifiedRemittance(
        int $remittanceId,
        int $providerId
    ): bool {
        $remittance = DB::table('rider_remittances')
            ->where('id', $remittanceId)
            ->where('logistics_provider_id', $providerId)
            ->lockForUpdate()
            ->first();

        if (!$remittance) {
            throw ValidationException::withMessages([
                'remittance' => 'Remittance not found.',
            ]);
        }

        if ($remittance->status !== 'verified') {
            throw ValidationException::withMessages([
                'remittance' =>
                    'Only verified remittances can enter the COD ledger.',
            ]);
        }

        $existing = DB::table('logistics_cod_wallet_entries')
            ->where('rider_remittance_id', $remittanceId)
            ->exists();

        if ($existing) {
            return false;
        }

        $allocated = (int) DB::table(
            'rider_remittance_allocations'
        )
            ->where('rider_remittance_id', $remittanceId)
            ->sum('amount_minor');

        $received = (int) $remittance->submitted_amount_minor;

        if ($allocated > $received) {
            throw ValidationException::withMessages([
                'remittance' =>
                    'Allocated COD exceeds the verified transfer.',
            ]);
        }

        DB::table('logistics_cod_wallet_entries')->insert([
            'logistics_provider_id' => $providerId,
            'rider_id' => $remittance->rider_id,
            'rider_remittance_id' => $remittanceId,
            'received_amount_minor' => $received,
            'allocated_amount_minor' => $allocated,
            'unallocated_amount_minor' => $received - $allocated,
            'source' => 'lumear_remittance',
            'recorded_at' => $remittance->verified_at ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function summary(int $providerId): array
    {
        $query = DB::table('logistics_cod_wallet_entries')
            ->where('logistics_provider_id', $providerId);

        return [
            'received_minor' => (int) (clone $query)
                ->sum('received_amount_minor'),

            'allocated_minor' => (int) (clone $query)
                ->sum('allocated_amount_minor'),

            'unallocated_minor' => (int) (clone $query)
                ->sum('unallocated_amount_minor'),
        ];
    }
}
