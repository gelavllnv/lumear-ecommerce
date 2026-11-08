<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LogisticsHeadAdminReportController extends Controller
{
    public function index(): View
    {
        return view('logistics.head-admin.reports');
    }

    public function download(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $hubs = DB::table('logistics_providers as provider')
            ->leftJoin('users as admin', 'admin.id', '=', 'provider.user_id')
            ->leftJoin('logistics_salary_location_tiers as tier', 'tier.id', '=', 'provider.salary_location_tier_id')
            ->leftJoin('logistics_hub_payroll_wallets as wallet', 'wallet.logistics_provider_id', '=', 'provider.id')
            ->where('provider.status', 'approved')
            ->selectSub(
                DB::table('logistics_hub_payroll_entries as entry')
                    ->selectRaw('COALESCE(SUM(CASE WHEN entry.direction = "credit" THEN entry.amount_minor ELSE -entry.amount_minor END), 0)')
                    ->whereColumn('entry.hub_payroll_wallet_id', 'wallet.id'),
                'rider_payroll_balance_minor'
            )
            ->selectSub(
                DB::table('logistics_hub_admin_payrolls as payroll')
                    ->selectRaw('COALESCE(SUM(payroll.salary_minor), 0)')
                    ->whereColumn('payroll.logistics_provider_id', 'provider.id')
                    ->where('payroll.status', 'paid')
                    ->whereDate('payroll.paid_at', '>=', $data['from'])
                    ->whereDate('payroll.paid_at', '<=', $data['to']),
                'hub_admin_salary_paid_minor'
            )
            ->selectSub(
                DB::table('rider_payrolls as rider_payroll')
                    ->selectRaw('COALESCE(SUM(rider_payroll.gross_pay_minor), 0)')
                    ->whereColumn('rider_payroll.logistics_provider_id', 'provider.id')
                    ->where('rider_payroll.status', 'paid')
                    ->whereDate('rider_payroll.paid_at', '>=', $data['from'])
                    ->whereDate('rider_payroll.paid_at', '<=', $data['to']),
                'rider_driver_salary_paid_minor'
            )
            ->addSelect(
                'provider.id',
                'provider.name',
                'provider.municipality_name',
                'provider.province_name',
                'admin.name as hub_admin_name',
                'tier.name as tier_name'
            )
            ->orderBy('provider.province_name')
            ->orderBy('provider.municipality_name')
            ->get();

        $centralFundMinor = (int) DB::table('logistics_central_fund_entries')
            ->selectRaw('COALESCE(SUM(CASE WHEN direction = "credit" THEN amount_minor ELSE -amount_minor END), 0) as total')
            ->value('total');

        $centralFundingMinor = (int) DB::table('logistics_central_fund_entries')
            ->where('type', 'central_funding')
            ->whereBetween(DB::raw('DATE(recorded_at)'), [$data['from'], $data['to']])
            ->sum('amount_minor');

        $hubFundingMinor = (int) DB::table('logistics_hub_payroll_entries')
            ->where('type', 'hub_payroll_funding')
            ->whereBetween(DB::raw('DATE(recorded_at)'), [$data['from'], $data['to']])
            ->sum('amount_minor');

        $summary = [
            'hub_count' => $hubs->count(),
            'central_fund_minor' => $centralFundMinor,
            'central_funding_minor' => $centralFundingMinor,
            'hub_funding_minor' => $hubFundingMinor,
            'hub_admin_salary_paid_minor' => (int) $hubs->sum('hub_admin_salary_paid_minor'),
            'rider_driver_salary_paid_minor' => (int) $hubs->sum('rider_driver_salary_paid_minor'),
        ];

        $pdf = Pdf::loadView('logistics.head-admin.reports-pdf', [
            'hubs' => $hubs,
            'summary' => $summary,
            'from' => $data['from'],
            'to' => $data['to'],
            'generatedAt' => now('Asia/Manila')->format('F d, Y h:i A').' PHT',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('lumear-logistics-head-admin-report-'.$data['from'].'-to-'.$data['to'].'.pdf');
    }
}
