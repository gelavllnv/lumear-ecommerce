<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\LogisticsSalaryDistributionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HubPayrollPaymentController extends Controller
{
    public function pay(
        int $id,
        LogisticsSalaryDistributionService $service
    ) {
        $providerId = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless(
            $providerId,
            403,
            'Approved Logistics hub required.'
        );

        $reference = $service->payRiderPayroll(
            (int) Auth::id(),
            (int) $providerId,
            $id
        );

        return back()->with(
            'payroll_success',
            'Rider payroll paid. Reference: '.$reference
        );
    }
}
