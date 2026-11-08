<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\LogisticsSalaryDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsHeadAdminSalaryDistributionController extends Controller
{
    public function index(
        LogisticsSalaryDistributionService $service
    ) {
        $providers = DB::table('logistics_providers as lp')
            ->leftJoin(
                'logistics_salary_location_tiers as tier',
                'tier.id',
                '=',
                'lp.salary_location_tier_id'
            )
            ->leftJoin(
                'logistics_admin_salary_matrix as matrix',
                function ($join) {
                    $join
                        ->on(
                            'matrix.location_tier_id',
                            '=',
                            'tier.id'
                        )
                        ->where(
                            'matrix.role',
                            '=',
                            'logistics_admin'
                        )
                        ->where(
                            'matrix.is_active',
                            '=',
                            true
                        );
                }
            )
            ->where('lp.status', 'approved')
            ->select(
                'lp.id',
                'lp.name',
                'lp.municipality_name',
                'lp.province_name',
                'tier.name as tier_name',
                'matrix.standard_monthly_salary_minor'
            )
            ->orderBy('lp.province_name')
            ->orderBy('lp.municipality_name')
            ->get();

        return view(
            'logistics.head-admin.salary-distribution',
            [
                'centralBalance' => $service->centralFundBalance(),

                'providers' => $providers,
            ]
        );
    }

    public function fundCentral(
        Request $request,
        LogisticsSalaryDistributionService $service
    ) {
        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'remarks' => [
                'required',
                'string',
                'max:500',
            ],
        ]);

        $reference = $service->recordCentralFunding(
            (int) Auth::id(),
            (int) round($data['amount'] * 100),
            $data['remarks']
        );

        return back()->with(
            'success',
            'Central Fund recorded. Reference: '.$reference
        );
    }

    public function fundHub(
        Request $request,
        int $id,
        LogisticsSalaryDistributionService $service
    ) {
        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $reference = $service->fundHub(
            (int) Auth::id(),
            $id,
            (int) round($data['amount'] * 100),
            $data['remarks'] ?? 'Hub rider payroll funding.'
        );

        return back()->with(
            'success',
            'Hub Rider Payroll Fund credited. Reference: '
            .$reference
        );
    }

    public function payHubAdmin(
        Request $request,
        int $id,
        LogisticsSalaryDistributionService $service
    ) {
        $data = $request->validate([
            'period_start' => [
                'required',
                'date_format:Y-m-d',
            ],
            'period_end' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:period_start',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $reference = $service->payHubAdminSalary(
            (int) Auth::id(),
            $id,
            $data['period_start'],
            $data['period_end'],
            $data['remarks'] ?? null
        );

        return back()->with(
            'success',
            'Hub Admin salary paid. Reference: '.$reference
        );
    }
}
