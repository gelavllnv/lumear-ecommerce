<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CodForwardingService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'forwarding' => $message,
        ]);
    }

    private function event(
        int $id,
        string $action,
        string $actor,
        string $description
    ): void {
        DB::table('logistics_cod_forwarding_events')->insert([
            'forwarding_id' => $id,
            'action' => $action,
            'actor' => $actor,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    public function summary(int $providerId): array
    {
        $receipts = DB::table('logistics_cod_wallet_entries as e')
            ->join(
                'rider_remittances as r',
                'r.id',
                '=',
                'e.rider_remittance_id'
            )
            ->where('e.logistics_provider_id', $providerId)
            ->where('r.logistics_provider_id', $providerId)
            ->where('r.status', 'verified');

        $eligible = (int) (clone $receipts)
            ->sum('e.allocated_amount_minor');

        $pending = (int) DB::table('logistics_cod_forwardings')
            ->where('logistics_provider_id', $providerId)
            ->where('status', 'pending_admin')
            ->sum('amount_minor');

        $completed = (int) DB::table('logistics_cod_forwardings')
            ->where('logistics_provider_id', $providerId)
            ->where('status', 'completed')
            ->sum('amount_minor');

        return [
            'eligible' => $eligible,
            'pending' => $pending,
            'completed' => $completed,
            'available' => max(
                0,
                $eligible - $pending - $completed
            ),
            'unallocated' => (int) (clone $receipts)
                ->sum('e.unallocated_amount_minor'),
        ];
    }

    public function create(
        int $providerId,
        int $userId,
        int $amountMinor,
        string $requestKey
    ): object {
        if ($amountMinor <= 0 || ! Str::isUuid($requestKey)) {
            $this->fail(
                'Enter a valid amount and reload the transfer form.'
            );
        }

        return DB::transaction(function () use (
            $providerId,
            $userId,
            $amountMinor,
            $requestKey
        ) {
            $provider = DB::table('logistics_providers')
                ->where('id', $providerId)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->first();

            abort_unless($provider, 403);

            $existing = DB::table('logistics_cod_forwardings')
                ->where('logistics_provider_id', $providerId)
                ->where('request_key', $requestKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((int) $existing->amount_minor !== $amountMinor) {
                    $this->fail(
                        'This form was already submitted with a different amount. Reload the page.'
                    );
                }

                return $existing;
            }

            $entries = DB::table('logistics_cod_wallet_entries as e')
                ->join(
                    'rider_remittances as r',
                    'r.id',
                    '=',
                    'e.rider_remittance_id'
                )
                ->where('e.logistics_provider_id', $providerId)
                ->where('r.logistics_provider_id', $providerId)
                ->where('r.status', 'verified')
                ->select('e.*')
                ->orderBy('e.id')
                ->lockForUpdate()
                ->get();

            $used = DB::table('logistics_cod_forwarding_items as i')
                ->join(
                    'logistics_cod_forwardings as f',
                    'f.id',
                    '=',
                    'i.forwarding_id'
                )
                ->where('f.logistics_provider_id', $providerId)
                ->whereIn('f.status', [
                    'pending_admin',
                    'completed',
                ])
                ->select(
                    'i.cod_wallet_entry_id',
                    'i.amount_minor'
                )
                ->lockForUpdate()
                ->get()
                ->groupBy('cod_wallet_entry_id')
                ->map(
                    fn ($rows) => (int) $rows->sum('amount_minor')
                );

            $available = 0;
            $sources = [];

            foreach ($entries as $entry) {
                $remaining =
                    (int) $entry->allocated_amount_minor
                    - (int) ($used[$entry->id] ?? 0);

                if (
                    $remaining < 0
                    || (int) $entry->allocated_amount_minor
                        > (int) $entry->received_amount_minor
                ) {
                    $this->fail(
                        'A source receipt needs reconciliation before forwarding.'
                    );
                }

                $sources[] = [
                    'id' => $entry->id,
                    'remaining' => $remaining,
                ];

                $available += $remaining;
            }

            if ($amountMinor > $available) {
                $this->fail(
                    'The amount exceeds your available verified COD balance.'
                );
            }

            $id = DB::table('logistics_cod_forwardings')
                ->insertGetId([
                    'logistics_provider_id' => $providerId,
                    'submitted_by' => $userId,
                    'reference' => 'CF-'.Str::ulid(),
                    'request_key' => $requestKey,
                    'amount_minor' => $amountMinor,
                    'currency' => 'PHP',
                    'status' => 'pending_admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $remaining = $amountMinor;

            foreach ($sources as $source) {
                $take = min(
                    $remaining,
                    $source['remaining']
                );

                if ($take > 0) {
                    DB::table('logistics_cod_forwarding_items')
                        ->insert([
                            'forwarding_id' => $id,
                            'cod_wallet_entry_id' => $source['id'],
                            'amount_minor' => $take,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $remaining -= $take;
                }

                if ($remaining === 0) {
                    break;
                }
            }

            $this->event(
                $id,
                'requested',
                'logistics',
                'Verified COD funds reserved for the main admin remittance wallet.'
            );

            return DB::table('logistics_cod_forwardings')
                ->find($id);
        }, 3);
    }

    public function payload(object $transfer): array
    {
        return [
            'reference' => $transfer->reference,
            'recipient' => 'lumear_main_admin_remittance',
            'logistics_provider_id' => (int) $transfer->logistics_provider_id,
            'amount_minor' => (int) $transfer->amount_minor,
            'currency' => $transfer->currency,
            'status' => $transfer->status,
            'admin_reference' => $transfer->admin_reference,
            'rejection_reason' => $transfer->rejection_reason,
            'created_at' => $transfer->created_at,
            'completed_at' => $transfer->completed_at,
            'sources' => DB::table('logistics_cod_forwarding_items as i')
                ->join(
                    'logistics_cod_wallet_entries as e',
                    'e.id',
                    '=',
                    'i.cod_wallet_entry_id'
                )
                ->where('i.forwarding_id', $transfer->id)
                ->orderBy('i.id')
                ->get([
                    'i.cod_wallet_entry_id',
                    'e.rider_remittance_id',
                    'i.amount_minor',
                ])
                ->map(fn ($item) => [
                    'cod_wallet_entry_id' => (int) $item->cod_wallet_entry_id,
                    'rider_remittance_id' => (int) $item->rider_remittance_id,
                    'amount_minor' => (int) $item->amount_minor,
                ])
                ->all(),
        ];
    }

    public function complete(
        string $reference,
        int $amountMinor,
        string $currency,
        string $adminReference
    ): object {
        $adminReference = trim($adminReference);

        if (
            $adminReference === ''
            || strlen($adminReference) > 120
        ) {
            $this->fail(
                'A valid admin wallet transaction reference is required.'
            );
        }

        try {
            return $this->transition(
                $reference,
                function ($transfer) use (
                    $amountMinor,
                    $currency,
                    $adminReference
                ) {
                    if (
                        (int) $transfer->amount_minor !== $amountMinor
                        || $currency !== $transfer->currency
                    ) {
                        $this->fail(
                            'Amount or currency does not match the forwarding request.'
                        );
                    }

                    if ($transfer->status === 'completed') {
                        if (
                            $transfer->admin_reference
                                !== $adminReference
                        ) {
                            $this->fail(
                                'This forwarding already has a different admin transaction reference.'
                            );
                        }

                        return;
                    }

                    if ($transfer->status !== 'pending_admin') {
                        $this->fail(
                            'A rejected forwarding cannot be completed. Reconcile the admin wallet first.'
                        );
                    }

                    $referenceUsed = DB::table('logistics_cod_forwardings')
                        ->where('admin_reference', $adminReference)
                        ->where('id', '!=', $transfer->id)
                        ->exists();

                    if ($referenceUsed) {
                        $this->fail(
                            'This admin wallet transaction reference is already used.'
                        );
                    }

                    DB::table('logistics_cod_forwardings')
                        ->where('id', $transfer->id)
                        ->update([
                            'status' => 'completed',
                            'admin_reference' => $adminReference,
                            'completed_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $this->event(
                        $transfer->id,
                        'completed',
                        'admin_bridge',
                        'Admin wallet credit confirmed: '
                            .$adminReference
                    );
                }
            );
        } catch (UniqueConstraintViolationException $exception) {
            $this->fail(
                'This admin wallet transaction reference is already used.'
            );
        }
    }

    public function reject(
        string $reference,
        string $reason
    ): object {
        $reason = trim($reason);

        if (
            mb_strlen($reason) < 5
            || mb_strlen($reason) > 500
        ) {
            $this->fail(
                'A rejection reason of 5 to 500 characters is required.'
            );
        }

        return $this->transition(
            $reference,
            function ($transfer) use ($reason) {
                if ($transfer->status === 'rejected') {
                    return;
                }

                if ($transfer->status !== 'pending_admin') {
                    $this->fail(
                        'A completed forwarding cannot be rejected.'
                    );
                }

                DB::table('logistics_cod_forwardings')
                    ->where('id', $transfer->id)
                    ->update([
                        'status' => 'rejected',
                        'rejection_reason' => $reason,
                        'rejected_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->event(
                    $transfer->id,
                    'rejected',
                    'admin_bridge',
                    $reason
                );
            }
        );
    }

    private function transition(
        string $reference,
        callable $callback
    ): object {
        $locator = DB::table('logistics_cod_forwardings')
            ->where('reference', $reference)
            ->first();

        abort_unless($locator, 404);

        return DB::transaction(function () use (
            $locator,
            $reference,
            $callback
        ) {
            DB::table('logistics_providers')
                ->where('id', $locator->logistics_provider_id)
                ->lockForUpdate()
                ->first();

            $transfer = DB::table('logistics_cod_forwardings')
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            abort_unless($transfer, 404);

            $callback($transfer);

            return DB::table('logistics_cod_forwardings')
                ->find($transfer->id);
        }, 3);
    }
}
