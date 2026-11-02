<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class LogisticsHeadAdminDashboardController extends Controller
{
    public function index()
    {
        $statistics = [
            'total' => DB::table('logistics_applications')->count(),

            'pending' => DB::table('logistics_applications')
                ->where('status', 'pending')
                ->count(),

            'approved' => DB::table('logistics_applications')
                ->where('status', 'approved')
                ->count(),

            'rejected' => DB::table('logistics_applications')
                ->where('status', 'rejected')
                ->count(),
        ];

        $applications = DB::table('logistics_applications')
            ->select(
                'id',
                'company_name',
                'first_name',
                'last_name',
                'city',
                'province',
                'status',
                'created_at'
            )
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('logistics.head-admin.dashboard', [
            'statistics' => $statistics,
            'applications' => $applications,
        ]);
    }
}
