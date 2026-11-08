<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $payments = DB::table('logistics_hub_admin_payrolls as payroll')
                ->join(
                    'logistics_providers as provider',
                    'provider.id',
                    '=',
                    'payroll.logistics_provider_id'
                )
                ->where('payroll.status', 'paid')
                ->whereNotNull('payroll.payment_reference')
                ->select(
                    'payroll.logistics_provider_id',
                    'payroll.payment_reference',
                    'payroll.salary_minor',
                    'payroll.paid_at',
                    'payroll.created_at'
                )
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $exists = DB::table('logistics_wallet_transactions')
                    ->where('reference', $payment->payment_reference)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('logistics_wallets')->insertOrIgnore([
                    'logistics_provider_id' => $payment->logistics_provider_id,
                    'currency' => 'PHP',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $wallet = DB::table('logistics_wallets')
                    ->where(
                        'logistics_provider_id',
                        $payment->logistics_provider_id
                    )
                    ->lockForUpdate()
                    ->first();

                DB::table('logistics_wallet_transactions')->insert([
                    'logistics_wallet_id' => $wallet->id,
                    'shipment_id' => null,
                    'reference' => $payment->payment_reference,
                    'type' => 'hub_admin_salary',
                    'shipping_fee_minor' => 0,
                    'commission_rate_basis_points' => 0,
                    'amount_minor' => $payment->salary_minor,
                    'earned_at' => $payment->paid_at ?? $payment->created_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('logistics_wallet_transactions')
            ->where('type', 'hub_admin_salary')
            ->delete();
    }
};
