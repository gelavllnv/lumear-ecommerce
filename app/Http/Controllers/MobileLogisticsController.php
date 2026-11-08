<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileLogisticsController extends Controller
{
    public function workspace(Request $request): JsonResponse
    {
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider?->status === 'approved', 403);

        return response()->json([
            'provider' => $provider->only('id', 'name'),
            'riders' => DB::table('riders as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('r.logistics_provider_id', $provider->id)->select('r.id', 'u.name', 'r.vehicle_type', 'r.job_type', 'r.management_status', 'r.is_active')->get(),
            'shipments' => DB::table('shipments as s')->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')->join('sellers as seller', 'seller.id', '=', 'so.seller_id')->leftJoin('logistics_sorting as sorting', 'sorting.shipment_id', '=', 's.id')->where('s.logistics_provider_id', $provider->id)->whereNotIn('s.status', ['delivered', 'returned'])->select('s.id', 's.tracking_code', 's.status', 's.rider_id', 'seller.name as seller_name', 'sorting.stage as sorting_stage', 'sorting.waybill_verified_at')->get(),
            'hubs' => LogisticsProvider::where('status', 'approved')->whereKeyNot($provider->id)->get(['id', 'name']),
            'tasks' => (new RiderTaskController)->index($request)->getData(true)['tasks'],
        ]);
    }
}
