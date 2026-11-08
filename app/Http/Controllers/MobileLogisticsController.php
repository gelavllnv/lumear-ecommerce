<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileLogisticsController extends Controller
{
    private function provider(Request $request): LogisticsProvider
    {
        $provider = $request->user()->logisticsProvider;

        abort_unless($provider?->status === 'approved', 403);

        return $provider;
    }

    public function workspace(Request $request): JsonResponse
    {
        $provider = $this->provider($request);

        return response()->json([
            'provider' => $provider->only('id', 'name'),
            'riders' => DB::table('riders as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('r.logistics_provider_id', $provider->id)->select('r.id', 'u.name', 'r.vehicle_type', 'r.job_type', 'r.management_status', 'r.is_active')->get(),
            'shipments' => DB::table('shipments as s')->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')->join('sellers as seller', 'seller.id', '=', 'so.seller_id')->leftJoin('logistics_sorting as sorting', 'sorting.shipment_id', '=', 's.id')->where('s.logistics_provider_id', $provider->id)->whereNotIn('s.status', ['delivered', 'returned'])->select('s.id', 's.tracking_code', 's.status', 's.rider_id', 'seller.name as seller_name', 'sorting.stage as sorting_stage', 'sorting.waybill_verified_at')->get(),
            'hubs' => LogisticsProvider::where('status', 'approved')->whereKeyNot($provider->id)->get(['id', 'name']),
            'tasks' => (new RiderTaskController)->index($request)->getData(true)['tasks'],
        ]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $provider = $this->provider($request);

        $deliveries = DB::table('shipments as s')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->leftJoin('logistics_sorting as sorting', 'sorting.shipment_id', '=', 's.id')
            ->where('s.logistics_provider_id', $provider->id)
            ->whereIn('s.status', [
                'assigned',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed',
                'returned',
            ])
            ->select(
                's.tracking_code',
                's.status',
                's.updated_at',
                'o.shipping_address',
                'sorting.stage as sorting_stage'
            )
            ->orderByDesc('s.updated_at')
            ->limit(100)
            ->get()
            ->map(function (object $shipment): array {
                $address = json_decode(
                    (string) $shipment->shipping_address,
                    true
                );
                $address = is_array($address) ? $address : [];

                return [
                    'tracking' => $shipment->tracking_code,
                    'status' => match ($shipment->status) {
                        'in_transit' => 'In Transit',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'failed' => 'Failed',
                        'returned' => 'Returned',
                        default => ucfirst(str_replace('_', ' ', $shipment->status)),
                    },
                    'destination' => collect([
                        $address['barangay'] ?? null,
                        $address['city'] ?? null,
                        $address['province'] ?? null,
                    ])->filter()->implode(', '),
                    'date' => $shipment->updated_at,
                    'sorting_stage' => $shipment->sorting_stage,
                ];
            });

        return response()->json(['deliveries' => $deliveries]);
    }
}
