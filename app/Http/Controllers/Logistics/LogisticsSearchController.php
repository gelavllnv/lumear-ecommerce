<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsSearchController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($data['q'] ?? '');

        if (mb_strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'results' => [],
            ]);
        }

        $providerId = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless(
            $providerId,
            403,
            'Approved Logistics account required.'
        );

        // Escape wildcard characters in user input.
        $escaped = str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $search
        );

        $like = '%' . $escaped . '%';

        /*
        |--------------------------------------------------------------------------
        | PARCELS / WAYBILLS
        |--------------------------------------------------------------------------
        */

        $shipments = DB::table('shipments as s')
            ->join(
                'seller_orders as so',
                'so.id',
                '=',
                's.seller_order_id'
            )
            ->join(
                'orders as o',
                'o.id',
                '=',
                'so.order_id'
            )
            ->where(
                's.logistics_provider_id',
                $providerId
            )
            ->where(
                'so.logistics_provider_id',
                $providerId
            )
            ->where(function ($query) use ($like) {
                $query
                    ->whereRaw(
                        "s.tracking_code LIKE ? ESCAPE '!'",
                        [$like]
                    )
                    ->orWhereRaw(
                        "o.reference LIKE ? ESCAPE '!'",
                        [$like]
                    );
            })
            ->select([
                's.id',
                's.tracking_code',
                's.status',
                'so.id as seller_order_id',
                'o.reference',
            ])
            ->orderByDesc('s.id')
            ->limit(8)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | PICKUP REQUESTS / ORDERS
        |--------------------------------------------------------------------------
        */

        $pickups = DB::table('seller_orders as so')
            ->join(
                'orders as o',
                'o.id',
                '=',
                'so.order_id'
            )
            ->leftJoin(
                'users as seller',
                'seller.id',
                '=',
                'so.seller_id'
            )
            ->where(
                'so.logistics_provider_id',
                $providerId
            )
            ->where(function ($query) use ($like) {
                $query
                    ->whereRaw(
                        "o.reference LIKE ? ESCAPE '!'",
                        [$like]
                    )
                    ->orWhereRaw(
                        "seller.name LIKE ? ESCAPE '!'",
                        [$like]
                    );
            })
            ->select([
                'so.id',
                'so.status',
                'o.reference',
                'seller.name as seller_name',
            ])
            ->orderByDesc('so.id')
            ->limit(8)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | RIDERS
        |--------------------------------------------------------------------------
        */

        $riders = DB::table('riders as r')
            ->join(
                'users as u',
                'u.id',
                '=',
                'r.user_id'
            )
            ->where(
                'r.logistics_provider_id',
                $providerId
            )
            ->where(function ($query) use ($like) {
                $query
                    ->whereRaw(
                        "u.name LIKE ? ESCAPE '!'",
                        [$like]
                    )
                    ->orWhereRaw(
                        "u.email LIKE ? ESCAPE '!'",
                        [$like]
                    );
            })
            ->select([
                'r.id',
                'r.management_status',
                'u.name',
                'u.email',
            ])
            ->orderBy('u.name')
            ->limit(8)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | BUILD SEARCH RESULTS
        |--------------------------------------------------------------------------
        */

        $results = collect();

        foreach ($shipments as $shipment) {
            $results->push([
                'type' => 'Parcel',
                'title' => $shipment->tracking_code,
                'subtitle' =>
                    'Order ' . $shipment->reference .
                    ' · ' . ucwords(
                        str_replace('_', ' ', $shipment->status)
                    ),
                'url' => route(
                    'logistics.pickup.show',
                    $shipment->seller_order_id
                ),
            ]);
        }

        foreach ($pickups as $pickup) {
            $results->push([
                'type' => 'Pickup Request',
                'title' => $pickup->reference,
                'subtitle' =>
                    ($pickup->seller_name ?? 'Seller') .
                    ' · ' . ucwords(
                        str_replace('_', ' ', $pickup->status)
                    ),
                'url' => route(
                    'logistics.pickup.show',
                    $pickup->id
                ),
            ]);
        }

        foreach ($riders as $rider) {
            $results->push([
                'type' => 'Rider',
                'title' => $rider->name,
                'subtitle' =>
                    'Rider #' . $rider->id .
                    ' · ' . (
                        $rider->management_status ?? 'Offline'
                    ),
                'url' => route('logistics.riders.index'),
            ]);
        }

        return response()->json([
            'success' => true,
            'query' => $search,
            'results' => $results
                ->take(20)
                ->values(),
        ]);
    }
}
