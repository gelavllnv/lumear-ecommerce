<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsWalletController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | GET AUTHENTICATED LOGISTICS PROVIDER
        |--------------------------------------------------------------------------
        */

        $provider = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        abort_unless(
            $provider,
            403,
            'Approved Logistics account required.'
        );

        /*
        |--------------------------------------------------------------------------
        | CREATE WALLET IF IT DOES NOT EXIST
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | WALLET SUMMARY
        |--------------------------------------------------------------------------
        */

        $transactionsQuery = DB::table(
            'logistics_wallet_transactions'
        )->where('logistics_wallet_id', $wallet->id);

        $totalEarningsMinor = (int) (clone $transactionsQuery)
            ->where('type', 'commission')
            ->sum('amount_minor');

        $completedDeliveries = (clone $transactionsQuery)
            ->where('type', 'commission')
            ->count();

        $thisMonthMinor = (int) (clone $transactionsQuery)
            ->where('type', 'commission')
            ->whereBetween('earned_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->sum('amount_minor');

        /*
        |--------------------------------------------------------------------------
        | AVAILABLE BALANCE
        |--------------------------------------------------------------------------
        |
        | Currently the wallet only supports commission credits.
        | No withdrawal or transfer system has been implemented.
        |
        */

        $availableBalanceMinor = $totalEarningsMinor;

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION HISTORY
        |--------------------------------------------------------------------------
        */

        $transactions = DB::table(
            'logistics_wallet_transactions as wt'
        )
            ->join(
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
            'totalEarningsMinor' => $totalEarningsMinor,
            'completedDeliveries' => $completedDeliveries,
            'thisMonthMinor' => $thisMonthMinor,
            'transactions' => $transactions,
        ]);
    }
}
