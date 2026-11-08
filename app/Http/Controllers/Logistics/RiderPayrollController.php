<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\RiderPayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderPayrollController extends Controller
{
    private function providerId(): int
    {
        $id = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless($id, 403, 'Approved logistics account required.');

        return (int) $id;
    }

    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::createFromFormat('!Y-m-d', $data['from']);
        $to = Carbon::createFromFormat('!Y-m-d', $data['to']);

        if ($from->diffInDays($to) > 365) {
            throw ValidationException::withMessages([
                'to' => 'Please select a period of 366 days or less.',
            ]);
        }

        return [$data['from'], $data['to']];
    }

    public function index(Request $request, RiderPayrollService $service)
    {
        $providerId = $this->providerId();

        $today = now('Asia/Manila');

        $from = $request->query(
            'from',
            $today->copy()->startOfMonth()->format('Y-m-d')
        );

        $to = $request->query(
            'to',
            $today->format('Y-m-d')
        );

        [$from, $to] = $this->period(
            new Request(['from' => $from, 'to' => $to])
        );

        $riders = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->select(
                'r.id',
                'r.is_active',
                'r.management_status',
                'u.name'
            )
            ->orderBy('u.name')
            ->get();

        $payrolls = DB::table('rider_payrolls')
            ->where('logistics_provider_id', $providerId)
            ->where('period_start', '<=', $to)
            ->where('period_end', '>=', $from)
            ->orderByDesc('created_at')
            ->get()
            ->keyBy('rider_id');

        $rows = $riders->map(function ($rider) use (
            $service,
            $providerId,
            $from,
            $to,
            $payrolls
        ) {
            $preview = $service->calculatePayroll(
                (int) $rider->id,
                $providerId,
                $from,
                $to
            );

            $saved = $payrolls->get($rider->id);

            return [
                'id' => $rider->id,
                'name' => $rider->name,
                'active' => (bool) $rider->is_active,
                'management_status' => $rider->management_status,
                'paid_days' => $preview['paid_days'],
                'deliveries' => $preview['successful_deliveries'],
                'base_minor' => $preview['base_salary_minor'],
                'commission_minor' => $preview['commission_minor'],
                'gross_minor' => $preview['gross_pay_minor'],
                'saved' => $saved,
            ];
        });

        return view('logistics.payroll.index', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'dailyRate' => RiderPayrollService::DAILY_SALARY_MINOR,
            'deliveryRate' => RiderPayrollService::DELIVERY_COMMISSION_MINOR,
            'riderCount' => $riders->count(),
            'totalBase' => $rows->sum('base_minor'),
            'totalCommission' => $rows->sum('commission_minor'),
            'totalGross' => $rows->sum('gross_minor'),
        ]);
    }

    public function store(Request $request, RiderPayrollService $service)
    {
        $providerId = $this->providerId();

        $data = $request->validate([
            'rider_id' => ['required', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        [$from, $to] = $this->period(
            new Request([
                'from' => $data['from'],
                'to' => $data['to'],
            ])
        );

        $rider = DB::table('riders')
            ->where('id', $data['rider_id'])
            ->where('logistics_provider_id', $providerId)
            ->first();

        abort_unless($rider, 404, 'Rider not found.');

        $service->createDraftPayroll(
            (int) $rider->id,
            $providerId,
            $from,
            $to
        );

        return redirect()
            ->route('logistics.riders.payroll.index', [
                'from' => $from,
                'to' => $to,
            ])
            ->with('payroll_success', 'Draft payroll generated successfully.');
    }
}
