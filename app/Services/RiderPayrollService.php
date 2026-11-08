<?php

namespace App\Services;

use App\Support\RiderJobType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderPayrollService
{
    public const DAILY_SALARY_MINOR = 63000;

    public const HUB_MONTHLY_SALARY_MINOR = 2000000;

    public const DELIVERY_COMMISSION_MINOR = 500;

    private function verifiedRider(
        int $riderId,
        int $providerId
    ): object {
        $rider = DB::table('riders')
            ->where('id', $riderId)
            ->where('logistics_provider_id', $providerId)
            ->first();

        if (! $rider) {
            throw ValidationException::withMessages([
                'rider' => 'Rider does not belong to this Logistics provider.',
            ]);
        }

        return $rider;
    }

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

    private function isDailySalaryRole(string $jobType): bool
    {
        return in_array($jobType, [
            RiderJobType::DELIVERY,
            RiderJobType::SELLER_PICKUP,
        ], true);
    }

    private function payrollRule(string $jobType): string
    {
        return $jobType === RiderJobType::HUB_TRANSFER
            ? 'monthly_prorated'
            : 'daily';
    }

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

        if (! in_array($attendanceStatus, $allowedStatuses, true)) {
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

        if ($attendanceStatus !== 'pending_review' && ! $verifiedBy) {
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
            $rider = $this->verifiedRider(
                $riderId,
                $providerId
            );

            DB::table('riders')
                ->where('id', $riderId)
                ->lockForUpdate()
                ->first();

            if ($this->hasLockedPayroll($riderId, $workDate)) {
                throw ValidationException::withMessages([
                    'work_date' => 'This date belongs to an approved or paid payroll period.',
                ]);
            }

            $existing = DB::table('rider_work_days')
                ->where('rider_id', $riderId)
                ->where('work_date', $workDate)
                ->first();

            $dailyBaseSalary = $this->isDailySalaryRole($rider->job_type)
                ? self::DAILY_SALARY_MINOR
                : 0;

            $values = [
                'logistics_provider_id' => $providerId,
                'attendance_status' => $attendanceStatus,
                'is_paid_day' => $isPaidDay,
                'base_salary_minor' => $dailyBaseSalary,
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

    public function creditDeliveredShipment(int $shipmentId): bool
    {
        return DB::transaction(function () use ($shipmentId) {
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->lockForUpdate()
                ->first();

            if (! $shipment) {
                return false;
            }

            if (
                strtolower(trim((string) $shipment->status))
                !== 'delivered'
            ) {
                return false;
            }

            if (
                ! $shipment->rider_id
                || ! $shipment->logistics_provider_id
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

            if (
                ! $rider
                || $rider->job_type !== RiderJobType::DELIVERY
            ) {
                return false;
            }

            $existing = DB::table('rider_delivery_commissions')
                ->where('shipment_id', $shipmentId)
                ->exists();

            if ($existing) {
                return false;
            }

            DB::table('rider_delivery_commissions')
                ->insert([
                    'rider_id' => $rider->id,
                    'logistics_provider_id' => $shipment->logistics_provider_id,
                    'shipment_id' => $shipmentId,
                    'amount_minor' => self::DELIVERY_COMMISSION_MINOR,
                    'delivery_status' => 'delivered',
                    'earned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return true;
        }, 3);
    }

    private function hubMonthlySalary(
        $workDays
    ): array {
        $grouped = $workDays->groupBy(function ($workDay) {
            return Carbon::parse(
                $workDay->work_date,
                'Asia/Manila'
            )->format('Y-m');
        });

        $salaryMinor = 0;
        $calendarDays = 0;
        $breakdown = [];

        foreach ($grouped as $month => $days) {
            $monthDate = Carbon::createFromFormat(
                'Y-m',
                $month,
                'Asia/Manila'
            );

            $daysInMonth = $monthDate->daysInMonth;
            $paidDays = $days->count();

            $monthSalary = intdiv(
                self::HUB_MONTHLY_SALARY_MINOR * $paidDays,
                $daysInMonth
            );

            $salaryMinor += $monthSalary;
            $calendarDays += $daysInMonth;

            $breakdown[] = [
                'month' => $month,
                'paid_days' => $paidDays,
                'calendar_days' => $daysInMonth,
                'salary_minor' => $monthSalary,
            ];
        }

        return [
            'salary_minor' => $salaryMinor,
            'calendar_days' => $calendarDays,
            'breakdown' => $breakdown,
        ];
    }

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

        $rider = $this->verifiedRider(
            $riderId,
            $providerId
        );

        $workDays = DB::table('rider_work_days')
            ->where('rider_id', $riderId)
            ->where('logistics_provider_id', $providerId)
            ->whereBetween('work_date', [
                $periodStart,
                $periodEnd,
            ])
            ->where('is_paid_day', true)
            ->whereNotNull('verified_by')
            ->get();

        $paidDays = $workDays->count();

        $payrollRule = $this->payrollRule($rider->job_type);

        $monthlySalaryMinor = 0;
        $calendarDays = 0;
        $monthlyBreakdown = [];

        if ($rider->job_type === RiderJobType::HUB_TRANSFER) {
            $hubSalary = $this->hubMonthlySalary($workDays);

            $baseSalaryMinor = $hubSalary['salary_minor'];
            $monthlySalaryMinor = self::HUB_MONTHLY_SALARY_MINOR;
            $calendarDays = $hubSalary['calendar_days'];
            $monthlyBreakdown = $hubSalary['breakdown'];
            $dailyRateMinor = 0;
        } else {
            $baseSalaryMinor = (int) $workDays
                ->sum('base_salary_minor');

            $dailyRateMinor = self::DAILY_SALARY_MINOR;
        }

        $successfulDeliveries = 0;
        $commissionMinor = 0;

        if ($rider->job_type === RiderJobType::DELIVERY) {
            $startTime = Carbon::parse(
                $periodStart,
                'Asia/Manila'
            )->startOfDay()->utc();

            $endTime = Carbon::parse(
                $periodEnd,
                'Asia/Manila'
            )->endOfDay()->utc();

            $commissions = DB::table('rider_delivery_commissions')
                ->where('rider_id', $riderId)
                ->where('logistics_provider_id', $providerId)
                ->whereBetween('earned_at', [
                    $startTime,
                    $endTime,
                ])
                ->get();

            $successfulDeliveries = $commissions->count();

            $commissionMinor = (int) $commissions
                ->sum('amount_minor');
        }

        $grossPayMinor = $baseSalaryMinor + $commissionMinor;

        return [
            'rider_id' => $riderId,
            'logistics_provider_id' => $providerId,
            'job_type' => $rider->job_type,
            'payroll_rule' => $payrollRule,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'paid_days' => $paidDays,
            'successful_deliveries' => $successfulDeliveries,
            'daily_rate_minor' => $dailyRateMinor,
            'monthly_salary_minor' => $monthlySalaryMinor,
            'calendar_days' => $calendarDays,
            'monthly_breakdown' => $monthlyBreakdown,
            'delivery_rate_minor' => $rider->job_type === RiderJobType::DELIVERY
                    ? self::DELIVERY_COMMISSION_MINOR
                    : 0,
            'base_salary_minor' => $baseSalaryMinor,
            'commission_minor' => $commissionMinor,
            'gross_pay_minor' => $grossPayMinor,
            'base_salary' => number_format(
                $baseSalaryMinor / 100,
                2,
                '.',
                ''
            ),
            'commission' => number_format(
                $commissionMinor / 100,
                2,
                '.',
                ''
            ),
            'gross_pay' => number_format(
                $grossPayMinor / 100,
                2,
                '.',
                ''
            ),
        ];
    }

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
            $this->verifiedRider($riderId, $providerId);

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
                    'logistics_provider_id' => $providerId,
                    'period_start' => $calculation['period_start'],
                    'period_end' => $calculation['period_end'],
                    'paid_days' => $calculation['paid_days'],
                    'successful_deliveries' => $calculation['successful_deliveries'],
                    'payroll_rule' => $calculation['payroll_rule'],
                    'monthly_salary_minor' => $calculation['monthly_salary_minor'],
                    'calendar_days' => $calculation['calendar_days'],
                    'base_salary_minor' => $calculation['base_salary_minor'],
                    'commission_minor' => $calculation['commission_minor'],
                    'gross_pay_minor' => $calculation['gross_pay_minor'],
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('rider_payroll_audit_logs')
                ->insert([
                    'rider_payroll_id' => $id,
                    'rider_id' => $riderId,
                    'logistics_provider_id' => $providerId,
                    'performed_by' => null,
                    'action' => 'draft_created',
                    'old_values' => null,
                    'new_values' => json_encode($calculation),
                    'remarks' => 'Draft payroll generated by system.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return DB::table('rider_payrolls')
                ->where('id', $id)
                ->first();
        }, 3);
    }
}
