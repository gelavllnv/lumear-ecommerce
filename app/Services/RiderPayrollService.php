<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderPayrollService
{
    /*
    |--------------------------------------------------------------------------
    | LUMEAR RIDER PAYROLL RATES
    |--------------------------------------------------------------------------
    |
    | All money is stored in centavos.
    |
    | Daily salary: PHP 630.00
    | Rider share per successful delivery: PHP 40.00
    |
    */

    public const DAILY_SALARY_MINOR = 63000;

    public const DELIVERY_COMMISSION_MINOR = 4000;

    /*
    |--------------------------------------------------------------------------
    | VERIFY RIDER AND LOGISTICS PROVIDER
    |--------------------------------------------------------------------------
    */

    private function verifiedRider(
        int $riderId,
        int $providerId
    ): object {
        $rider = DB::table('riders')
            ->where('id', $riderId)
            ->where('logistics_provider_id', $providerId)
            ->first();

        if (!$rider) {
            throw ValidationException::withMessages([
                'rider' => 'Rider does not belong to this Logistics provider.',
            ]);
        }

        return $rider;
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY DATE
    |--------------------------------------------------------------------------
    */

    private function validDate(string $date): string
    {
        try {
            return Carbon::createFromFormat(
                '!Y-m-d',
                $date,
                'Asia/Manila'
            )->format('Y-m-d');
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'date' => 'Invalid date. Use YYYY-MM-DD.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK FOR LOCKED PAYROLL PERIOD
    |--------------------------------------------------------------------------
    |
    | Approved and paid payrolls must not be
    | silently changed by attendance updates.
    |
    */

    private function hasLockedPayroll(
        int $riderId,
        string $date
    ): bool {
        return DB::table('rider_payrolls')
            ->where('rider_id', $riderId)
            ->whereIn('status', [
                'approved',
                'paid',
            ])
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | RECORD RIDER ATTENDANCE
    |--------------------------------------------------------------------------
    |
    | One record per rider per work date.
    |
    | Paid days are explicitly verified.
    | Deliveries do not determine base pay.
    |
    | This method must be called only from
    | an authorized Logistics backend action.
    |
    */

    public function recordWorkDay(
        int $riderId,
        int $providerId,
        string $workDate,
        string $attendanceStatus,
        bool $isPaidDay,
        ?int $verifiedBy = null,
        ?string $remarks = null
    ): object {
        $workDate = $this->validDate($workDate);

        $allowedStatuses = [
            'present',
            'absent',
            'approved_leave',
            'pending_review',
        ];

        if (!in_array(
            $attendanceStatus,
            $allowedStatuses,
            true
        )) {
            throw ValidationException::withMessages([
                'attendance_status' => 'Invalid attendance status.',
            ]);
        }

        if ($isPaidDay && $attendanceStatus === 'pending_review') {
            throw ValidationException::withMessages([
                'attendance_status' => 'Pending attendance cannot be marked as paid.',
            ]);
        }

        if ($isPaidDay && $attendanceStatus === 'absent') {
            throw ValidationException::withMessages([
                'attendance_status' => 'An absent day requires a separate authorized paid-leave classification.',
            ]);
        }

        if ($attendanceStatus !== 'pending_review' && !$verifiedBy) {
            throw ValidationException::withMessages([
                'verified_by' => 'An authorized verifier is required.',
            ]);
        }

        return DB::transaction(function () use (
            $riderId,
            $providerId,
            $workDate,
            $attendanceStatus,
            $isPaidDay,
            $verifiedBy,
            $remarks
        ) {
            $this->verifiedRider(
                $riderId,
                $providerId
            );

            // Serialize attendance and payroll changes for this rider.
            DB::table('riders')
                ->where('id', $riderId)
                ->lockForUpdate()
                ->first();

            if ($this->hasLockedPayroll(
                $riderId,
                $workDate
            )) {
                throw ValidationException::withMessages([
                    'work_date' => 'This date belongs to an approved or paid payroll period.',
                ]);
            }

            $existing = DB::table('rider_work_days')
                ->where('rider_id', $riderId)
                ->where('work_date', $workDate)
                ->first();

            $values = [
                'logistics_provider_id' => $providerId,
                'attendance_status' => $attendanceStatus,
                'is_paid_day' => $isPaidDay,
                'base_salary_minor' => self::DAILY_SALARY_MINOR,
                'remarks' => $remarks,
                'verified_by' => $verifiedBy,
                'verified_at' => $verifiedBy ? now() : null,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('rider_work_days')
                    ->where('id', $existing->id)
                    ->update($values);

                $id = $existing->id;
            } else {
                $id = DB::table('rider_work_days')
                    ->insertGetId(array_merge(
                        $values,
                        [
                            'rider_id' => $riderId,
                            'work_date' => $workDate,
                            'created_at' => now(),
                        ]
                    ));
            }

            return DB::table('rider_work_days')
                ->where('id', $id)
                ->first();
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | CREDIT SUCCESSFUL DELIVERY COMMISSION
    |--------------------------------------------------------------------------
    |
    | PHP 40.00 rider share per successfully delivered shipment.
    |
    | Does not award commission for failed
    | deliveries or returned shipments.
    |
    | Unique shipment_id prevents duplicates.
    |
    */

    public function creditDeliveredShipment(
        int $shipmentId,
        ?string $earnedAt = null,
    ): bool {
        return DB::transaction(function () use (
            $shipmentId,
            $earnedAt,
        ) {
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->lockForUpdate()
                ->first();

            if (!$shipment) {
                return false;
            }

            if (strtolower(trim(
                (string) $shipment->status
            )) !== 'delivered') {
                return false;
            }

            if (
                !$shipment->rider_id ||
                !$shipment->logistics_provider_id
            ) {
                return false;
            }

            $rider = DB::table('riders')
                ->where('id', $shipment->rider_id)
                ->where(
                    'logistics_provider_id',
                    $shipment->logistics_provider_id
                )
                ->first();

            if (!$rider) {
                return false;
            }

            $existing = DB::table(
                'rider_delivery_commissions'
            )
                ->where('shipment_id', $shipmentId)
                ->exists();

            if ($existing) {
                return false;
            }

            /*
            |----------------------------------------------------------
            | Do not backdate earnings from shipment creation.
            | The credit is recorded when the verified delivery
            | is processed by this service.
            |----------------------------------------------------------
            */

            $earnedAt = $earnedAt === null
                ? now()
                : Carbon::parse($earnedAt);

            DB::table('rider_delivery_commissions')
                ->insert([
                    'rider_id' => $rider->id,

                    'logistics_provider_id' =>
                        $shipment->logistics_provider_id,

                    'shipment_id' => $shipmentId,

                    'amount_minor' =>
                        self::DELIVERY_COMMISSION_MINOR,

                    'delivery_status' => 'delivered',

                    'earned_at' => $earnedAt,

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);

            return true;
        }, 3);
    }

    public function syncDeliveredShipments(
        int $riderId,
        int $providerId,
    ): int {
        $shipments = DB::table('shipments as s')
            ->where('s.rider_id', $riderId)
            ->where('s.logistics_provider_id', $providerId)
            ->where('s.status', 'delivered')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('rider_delivery_commissions')
                    ->whereColumn('rider_delivery_commissions.shipment_id', 's.id');
            })
            ->select('s.id', 's.updated_at')
            ->selectSub(
                DB::table('delivery_events')
                    ->select('occurred_at')
                    ->whereColumn('delivery_events.shipment_id', 's.id')
                    ->where('delivery_events.status', 'delivered')
                    ->orderByDesc('occurred_at')
                    ->limit(1),
                'delivery_occurred_at',
            )
            ->orderBy('s.id')
            ->get();

        $credited = 0;
        foreach ($shipments as $shipment) {
            if ($this->creditDeliveredShipment(
                (int) $shipment->id,
                $shipment->delivery_occurred_at ?? $shipment->updated_at,
            )) {
                $credited++;
            }
        }

        return $credited;
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE RIDER PAYROLL
    |--------------------------------------------------------------------------
    |
    | Formula:
    |
    | Base salary = paid days x PHP 630
    |
    | Commission = delivered shipments x PHP 40
    |
    | Gross pay = base salary + commission
    |
    | This returns a preview.
    | It does not create a payment.
    |
    */

    public function calculatePayroll(
        int $riderId,
        int $providerId,
        string $periodStart,
        string $periodEnd
    ): array {
        $periodStart = $this->validDate($periodStart);
        $periodEnd = $this->validDate($periodEnd);

        if ($periodEnd < $periodStart) {
            throw ValidationException::withMessages([
                'period_end' => 'Payroll end date cannot be earlier than the start date.',
            ]);
        }

        $this->verifiedRider(
            $riderId,
            $providerId
        );

        /*
        |--------------------------------------------------------------------------
        | COUNT ELIGIBLE PAID WORK DAYS
        |--------------------------------------------------------------------------
        */

        $workDays = DB::table('rider_work_days')
            ->where('rider_id', $riderId)
            ->where(
                'logistics_provider_id',
                $providerId
            )
            ->whereBetween('work_date', [
                $periodStart,
                $periodEnd,
            ])
            ->where('is_paid_day', true)
            ->whereNotNull('verified_by')
            ->get();

        $paidDays = $workDays->count();

        $baseSalaryMinor = (int) $workDays
            ->sum('base_salary_minor');

        /*
        |--------------------------------------------------------------------------
        | COUNT SUCCESSFUL DELIVERY COMMISSIONS
        |--------------------------------------------------------------------------
        */

        $startTime = Carbon::parse(
            $periodStart,
            'Asia/Manila'
        )->startOfDay()->utc();

        $endTime = Carbon::parse(
            $periodEnd,
            'Asia/Manila'
        )->endOfDay()->utc();

        $commissions = DB::table(
            'rider_delivery_commissions'
        )
            ->where('rider_id', $riderId)
            ->where(
                'logistics_provider_id',
                $providerId
            )
            ->whereBetween('earned_at', [
                $startTime,
                $endTime,
            ])
            ->get();

        $successfulDeliveries = $commissions->count();

        $commissionMinor = (int) $commissions
            ->sum('amount_minor');

        $grossPayMinor = $baseSalaryMinor
            + $commissionMinor;

        return [
            'rider_id' => $riderId,

            'logistics_provider_id' => $providerId,

            'period_start' => $periodStart,

            'period_end' => $periodEnd,

            'paid_days' => $paidDays,

            'successful_deliveries' =>
                $successfulDeliveries,

            'daily_rate_minor' =>
                self::DAILY_SALARY_MINOR,

            'delivery_rate_minor' =>
                self::DELIVERY_COMMISSION_MINOR,

            'base_salary_minor' =>
                $baseSalaryMinor,

            'commission_minor' =>
                $commissionMinor,

            'gross_pay_minor' =>
                $grossPayMinor,

            'base_salary' =>
                number_format(
                    $baseSalaryMinor / 100,
                    2,
                    '.',
                    ''
                ),

            'commission' =>
                number_format(
                    $commissionMinor / 100,
                    2,
                    '.',
                    ''
                ),

            'gross_pay' =>
                number_format(
                    $grossPayMinor / 100,
                    2,
                    '.',
                    ''
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE DRAFT PAYROLL
    |--------------------------------------------------------------------------
    |
    | Draft payroll is not yet an approved payment.
    |
    | Prevents overlapping payroll periods.
    |
    */

    public function createDraftPayroll(
        int $riderId,
        int $providerId,
        string $periodStart,
        string $periodEnd
    ): object {
        return DB::transaction(function () use (
            $riderId,
            $providerId,
            $periodStart,
            $periodEnd
        ) {
            $this->verifiedRider(
                $riderId,
                $providerId
            );

            DB::table('riders')
                ->where('id', $riderId)
                ->lockForUpdate()
                ->first();

            $calculation = $this->calculatePayroll(
                $riderId,
                $providerId,
                $periodStart,
                $periodEnd
            );

            $overlap = DB::table('rider_payrolls')
                ->where('rider_id', $riderId)
                ->where('status', '!=', 'void')
                ->where(
                    'period_start',
                    '<=',
                    $calculation['period_end']
                )
                ->where(
                    'period_end',
                    '>=',
                    $calculation['period_start']
                )
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'period' => 'A payroll already exists for an overlapping date range.',
                ]);
            }

            $id = DB::table('rider_payrolls')
                ->insertGetId([
                    'rider_id' => $riderId,

                    'logistics_provider_id' =>
                        $providerId,

                    'period_start' =>
                        $calculation['period_start'],

                    'period_end' =>
                        $calculation['period_end'],

                    'paid_days' =>
                        $calculation['paid_days'],

                    'successful_deliveries' =>
                        $calculation['successful_deliveries'],

                    'base_salary_minor' =>
                        $calculation['base_salary_minor'],

                    'commission_minor' =>
                        $calculation['commission_minor'],

                    'gross_pay_minor' =>
                        $calculation['gross_pay_minor'],

                    'status' => 'draft',

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);

            DB::table('rider_payroll_audit_logs')
                ->insert([
                    'rider_payroll_id' => $id,

                    'rider_id' => $riderId,

                    'logistics_provider_id' =>
                        $providerId,

                    'performed_by' => null,

                    'action' => 'draft_created',

                    'old_values' => null,

                    'new_values' => json_encode(
                        $calculation
                    ),

                    'remarks' =>
                        'Draft payroll generated by system.',

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);

            return DB::table('rider_payrolls')
                ->where('id', $id)
                ->first();
        }, 3);
    }
}
