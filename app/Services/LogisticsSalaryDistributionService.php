<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogisticsSalaryDistributionService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'salary' => $message,
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.strtoupper(Str::ulid());
    }

    private function audit(
        string $reference,
        string $action,
        int $performedBy,
        array $details = []
    ): void {
        DB::table('logistics_salary_distribution_audit_logs')->insert([
            'reference' => $reference,
            'action' => $action,
            'performed_by' => $performedBy,
            'details' => json_encode($details),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function centralWallet(): object
    {
        DB::table('logistics_central_fund_wallets')->insertOrIgnore([
            'id' => 1,
            'currency' => 'PHP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('logistics_central_fund_wallets')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();
    }

    private function centralBalance(int $walletId): int
    {
        return (int) DB::table('logistics_central_fund_entries')
            ->where('central_wallet_id', $walletId)
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN direction = 'credit' THEN amount_minor
                        ELSE -amount_minor
                    END
                ), 0) AS balance
            ")
            ->value('balance');
    }

    private function hubWallet(int $providerId): object
    {
        DB::table('logistics_hub_payroll_wallets')->insertOrIgnore([
            'logistics_provider_id' => $providerId,
            'currency' => 'PHP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('logistics_hub_payroll_wallets')
            ->where('logistics_provider_id', $providerId)
            ->lockForUpdate()
            ->first();
    }

    private function hubBalance(int $walletId): int
    {
        return (int) DB::table('logistics_hub_payroll_entries')
            ->where('hub_payroll_wallet_id', $walletId)
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN direction = 'credit' THEN amount_minor
                        ELSE -amount_minor
                    END
                ), 0) AS balance
            ")
            ->value('balance');
    }

    private function personalWallet(int $userId): object
    {
        DB::table('logistics_personal_salary_wallets')->insertOrIgnore([
            'user_id' => $userId,
            'currency' => 'PHP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('logistics_personal_salary_wallets')
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    public function centralFundBalance(): int
    {
        return DB::transaction(function () {
            $wallet = $this->centralWallet();

            return $this->centralBalance($wallet->id);
        }, 3);
    }

    public function hubFundBalance(int $providerId): int
    {
        return DB::transaction(function () use ($providerId) {
            $wallet = $this->hubWallet($providerId);

            return $this->hubBalance($wallet->id);
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Head Admin manually records money added to Central Logistics Fund.
    |--------------------------------------------------------------------------
    */

    public function recordCentralFunding(
        int $headAdminId,
        int $amountMinor,
        string $remarks
    ): string {
        if ($amountMinor <= 0) {
            $this->fail('Funding amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $headAdminId,
            $amountMinor,
            $remarks
        ) {
            $wallet = $this->centralWallet();
            $reference = $this->reference('LOG-FUND');

            DB::table('logistics_central_fund_entries')->insert([
                'central_wallet_id' => $wallet->id,
                'reference' => $reference,
                'direction' => 'credit',
                'type' => 'central_funding',
                'amount_minor' => $amountMinor,
                'performed_by' => $headAdminId,
                'remarks' => $remarks,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit(
                $reference,
                'central_fund_added',
                $headAdminId,
                [
                    'amount_minor' => $amountMinor,
                    'remarks' => $remarks,
                ]
            );

            return $reference;
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Head Admin funds a Hub's protected Rider Payroll Fund.
    |--------------------------------------------------------------------------
    */

    public function fundHub(
        int $headAdminId,
        int $providerId,
        int $amountMinor,
        string $remarks
    ): string {
        if ($amountMinor <= 0) {
            $this->fail('Hub funding amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $headAdminId,
            $providerId,
            $amountMinor,
            $remarks
        ) {
            $provider = DB::table('logistics_providers')
                ->where('id', $providerId)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->first();

            abort_unless($provider, 404, 'Hub not found.');

            $central = $this->centralWallet();

            if ($this->centralBalance($central->id) < $amountMinor) {
                $this->fail('Central Logistics Fund has insufficient balance.');
            }

            $hub = $this->hubWallet($providerId);
            $reference = $this->reference('HUB-FUND');

            DB::table('logistics_central_fund_entries')->insert([
                'central_wallet_id' => $central->id,
                'reference' => $reference,
                'direction' => 'debit',
                'type' => 'hub_payroll_funding',
                'amount_minor' => $amountMinor,
                'performed_by' => $headAdminId,
                'remarks' => $remarks,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('logistics_hub_payroll_entries')->insert([
                'hub_payroll_wallet_id' => $hub->id,
                'reference' => $reference,
                'direction' => 'credit',
                'type' => 'hub_payroll_funding',
                'amount_minor' => $amountMinor,
                'performed_by' => $headAdminId,
                'remarks' => $remarks,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit(
                $reference,
                'hub_payroll_fund_sent',
                $headAdminId,
                [
                    'logistics_provider_id' => $providerId,
                    'amount_minor' => $amountMinor,
                    'remarks' => $remarks,
                ]
            );

            return $reference;
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Head Admin pays the Hub Admin's personal salary.
    |--------------------------------------------------------------------------
    */

    public function payHubAdminSalary(
        int $headAdminId,
        int $providerId,
        string $periodStart,
        string $periodEnd,
        ?string $remarks = null
    ): string {
        return DB::transaction(function () use (
            $headAdminId,
            $providerId,
            $periodStart,
            $periodEnd,
            $remarks
        ) {
            $provider = DB::table('logistics_providers')
                ->where('id', $providerId)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->first();

            abort_unless($provider, 404, 'Hub not found.');

            if (! $provider->salary_location_tier_id) {
                $this->fail('This hub has no assigned salary location tier.');
            }

            $existingPayroll = DB::table('logistics_hub_admin_payrolls')
                ->where('logistics_provider_id', $providerId)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->lockForUpdate()
                ->first();

            if ($existingPayroll && $existingPayroll->status === 'paid') {
                $this->fail('This Hub Admin salary was already paid.');
            }

            $matrix = DB::table('logistics_admin_salary_matrix')
                ->where('role', 'logistics_admin')
                ->where('location_tier_id', $provider->salary_location_tier_id)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $periodEnd)
                ->where(function ($query) use ($periodStart) {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $periodStart);
                })
                ->orderByDesc('effective_from')
                ->lockForUpdate()
                ->first();

            if (! $matrix) {
                $this->fail(
                    'No active location-based salary matrix exists for this hub.'
                );
            }

            $salaryMinor = (int) $matrix->standard_monthly_salary_minor;

            if ($salaryMinor <= 0) {
                $this->fail('The configured Hub Admin salary must be greater than zero.');
            }

            $central = $this->centralWallet();

            if ($this->centralBalance($central->id) < $salaryMinor) {
                $this->fail('Central Logistics Fund has insufficient balance.');
            }

            DB::table('logistics_wallets')->insertOrIgnore([
                'logistics_provider_id' => $providerId,
                'currency' => 'PHP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adminWallet = DB::table('logistics_wallets')
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $adminWallet,
                500,
                'Unable to create the Hub Admin E-Wallet.'
            );
            $reference = $this->reference('HUB-ADMIN-PAY');

            DB::table('logistics_central_fund_entries')->insert([
                'central_wallet_id' => $central->id,
                'reference' => $reference,
                'direction' => 'debit',
                'type' => 'hub_admin_salary',
                'amount_minor' => $salaryMinor,
                'performed_by' => $headAdminId,
                'remarks' => $remarks,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('logistics_wallet_transactions')->insert([
                'logistics_wallet_id' => $adminWallet->id,
                'shipment_id' => null,
                'reference' => $reference,
                'type' => 'hub_admin_salary',
                'shipping_fee_minor' => 0,
                'commission_rate_basis_points' => 0,
                'amount_minor' => $salaryMinor,
                'earned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payrollValues = [
                'hub_admin_user_id' => $provider->user_id,
                'salary_matrix_id' => $matrix->id,
                'salary_minor' => $salaryMinor,
                'status' => 'paid',
                'payment_reference' => $reference,
                'paid_by' => $headAdminId,
                'paid_at' => now(),
                'remarks' => $remarks,
                'updated_at' => now(),
            ];

            if ($existingPayroll) {
                DB::table('logistics_hub_admin_payrolls')
                    ->where('id', $existingPayroll->id)
                    ->update($payrollValues);
            } else {
                DB::table('logistics_hub_admin_payrolls')->insert(
                    array_merge($payrollValues, [
                        'logistics_provider_id' => $providerId,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'created_at' => now(),
                    ])
                );
            }

            $this->audit(
                $reference,
                'hub_admin_salary_paid',
                $headAdminId,
                [
                    'logistics_provider_id' => $providerId,
                    'hub_admin_user_id' => $provider->user_id,
                    'amount_minor' => $salaryMinor,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ]
            );

            return $reference;
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Hub Admin pays a Rider / Pickup Rider / Hub-to-Hub Driver.
    |--------------------------------------------------------------------------
    */

    public function payRiderPayroll(
        int $hubAdminId,
        int $providerId,
        int $payrollId
    ): string {
        return DB::transaction(function () use (
            $hubAdminId,
            $providerId,
            $payrollId
        ) {
            $provider = DB::table('logistics_providers')
                ->where('id', $providerId)
                ->where('user_id', $hubAdminId)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->first();

            abort_unless($provider, 403);

            $payroll = DB::table('rider_payrolls')
                ->where('id', $payrollId)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($payroll, 404);

            if ($payroll->status === 'paid') {
                $this->fail('This rider payroll is already paid.');
            }

            if ($payroll->status === 'void') {
                $this->fail('A void rider payroll cannot be paid.');
            }

            if (! in_array($payroll->status, ['draft', 'approved'], true)) {
                $this->fail('Only draft or approved rider payrolls can be paid.');
            }

            $rider = DB::table('riders')
                ->where('id', $payroll->rider_id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($rider, 404);

            $amountMinor = (int) $payroll->gross_pay_minor;

            if ($amountMinor <= 0) {
                $this->fail('This payroll has no payable amount.');
            }

            $hubWallet = $this->hubWallet($providerId);

            if ($this->hubBalance($hubWallet->id) < $amountMinor) {
                $this->fail(
                    'Your Hub Rider Payroll Fund has insufficient balance. Ask the Head Admin to fund your hub.'
                );
            }

            $riderWallet = $this->personalWallet((int) $rider->user_id);
            $reference = $this->reference('RIDER-PAY');

            DB::table('logistics_hub_payroll_entries')->insert([
                'hub_payroll_wallet_id' => $hubWallet->id,
                'reference' => $reference,
                'direction' => 'debit',
                'type' => 'rider_salary_payment',
                'amount_minor' => $amountMinor,
                'rider_payroll_id' => $payroll->id,
                'performed_by' => $hubAdminId,
                'remarks' => 'Rider payroll payment.',
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('logistics_personal_salary_entries')->insert([
                'personal_wallet_id' => $riderWallet->id,
                'reference' => $reference,
                'direction' => 'credit',
                'type' => 'rider_salary_payment',
                'amount_minor' => $amountMinor,
                'rider_payroll_id' => $payroll->id,
                'performed_by' => $hubAdminId,
                'remarks' => 'Salary from Hub Rider Payroll Fund.',
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rider_payrolls')
                ->where('id', $payroll->id)
                ->update([
                    'status' => 'paid',
                    'approved_by' => $hubAdminId,
                    'approved_at' => now(),
                    'paid_by' => $hubAdminId,
                    'paid_at' => now(),
                    'payment_reference' => $reference,
                    'updated_at' => now(),
                ]);

            $this->audit(
                $reference,
                'rider_payroll_paid',
                $hubAdminId,
                [
                    'logistics_provider_id' => $providerId,
                    'rider_id' => $rider->id,
                    'rider_payroll_id' => $payroll->id,
                    'amount_minor' => $amountMinor,
                ]
            );

            return $reference;
        }, 3);
    }
}
