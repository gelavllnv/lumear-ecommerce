<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsWalletController extends Controller
{
    public function index()
    {
        $provider = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        abort_unless(
            $provider,
            403,
            'Approved Logistics account required.'
        );

        DB::table('logistics_wallets')->insertOrIgnore([
            'logistics_provider_id' => $provider->id,
            'currency' => 'PHP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wallet = DB::table('logistics_wallets')
            ->where('logistics_provider_id', $provider->id)
            ->first();

        abort_unless($wallet, 500, 'Unable to load wallet.');

        $baseQuery = DB::table('logistics_wallet_transactions')
            ->where('logistics_wallet_id', $wallet->id);

        $availableBalanceMinor = (int) (clone $baseQuery)
            ->sum('amount_minor');

        $totalCommissionMinor = (int) (clone $baseQuery)
            ->where('type', 'commission')
            ->sum('amount_minor');

        $totalSalaryMinor = (int) (clone $baseQuery)
            ->where('type', 'hub_admin_salary')
            ->sum('amount_minor');

        $completedDeliveries = (clone $baseQuery)
            ->where('type', 'commission')
            ->count();

        $thisMonthMinor = (int) (clone $baseQuery)
            ->whereBetween('earned_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->sum('amount_minor');

        $transactions = DB::table('logistics_wallet_transactions as wt')
            ->leftJoin(
                'shipments as s',
                's.id',
                '=',
                'wt.shipment_id'
            )
            ->leftJoin(
                'seller_orders as so',
                'so.id',
                '=',
                's.seller_order_id'
            )
            ->leftJoin(
                'orders as o',
                'o.id',
                '=',
                'so.order_id'
            )
            ->where('wt.logistics_wallet_id', $wallet->id)
            ->select(
                'wt.id',
                'wt.type',
                'wt.reference',
                'wt.shipping_fee_minor',
                'wt.commission_rate_basis_points',
                'wt.amount_minor',
                'wt.earned_at',
                's.tracking_code',
                'o.reference as order_reference'
            )
            ->orderByDesc('wt.earned_at')
            ->orderByDesc('wt.id')
            ->paginate(10);

        return view('logistics.wallet.index', [
            'provider' => $provider,
            'wallet' => $wallet,
            'availableBalanceMinor' => $availableBalanceMinor,
            'totalEarningsMinor' => $totalCommissionMinor,
            'totalSalaryMinor' => $totalSalaryMinor,
            'completedDeliveries' => $completedDeliveries,
            'thisMonthMinor' => $thisMonthMinor,
            'transactions' => $transactions,
        ]);
    }
}
