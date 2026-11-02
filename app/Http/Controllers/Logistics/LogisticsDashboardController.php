<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        abort_unless($user, 401);

        /*
        |--------------------------------------------------------------------------
        | AUTHENTICATED LOGISTICS PROVIDER
        |--------------------------------------------------------------------------
        */

        $provider = DB::table('logistics_providers')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        abort_unless(
            $provider,
            403,
            'Your Logistics provider account is not approved.'
        );

        /*
        |--------------------------------------------------------------------------
        | REGISTRATION INFORMATION
        |--------------------------------------------------------------------------
        */

        $application = DB::table('logistics_applications')
            ->where('logistics_provider_id', $provider->id)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | PROVIDER-SCOPED SHIPMENTS
        |--------------------------------------------------------------------------
        |
        | Only shipments assigned to the logged-in
        | Logistics provider are included.
        |
        */

        $shipments = DB::table('shipments')
            ->where('logistics_provider_id', $provider->id);

        /*
        |--------------------------------------------------------------------------
        | PENDING PICKUP REQUESTS
        |--------------------------------------------------------------------------
        |
        | Seller orders that are ready to ship and
        | assigned to this Logistics provider.
        |
        */

        $pendingPickups = DB::table('seller_orders')
            ->where('logistics_provider_id', $provider->id)
            ->where('status', 'ready_to_ship')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | INCOMING PARCELS
        |--------------------------------------------------------------------------
        |
        | Shipments that have been picked up or
        | are currently in transit.
        |
        */

        $incomingParcels = (clone $shipments)
            ->whereIn('status', [
                'picked_up',
                'in_transit',
            ])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | SORTING QUEUE
        |--------------------------------------------------------------------------
        |
        | The current schema has no separate sorting
        | status. This remains zero until we implement
        | sorting-center receiving and sorting records.
        |
        */

        $sortingQueue = 0;

        /*
        |--------------------------------------------------------------------------
        | RIDER STATISTICS
        |--------------------------------------------------------------------------
        */

        $activeRiders = DB::table('riders')
            ->where('logistics_provider_id', $provider->id)
            ->where('is_active', 1)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | OUT FOR DELIVERY
        |--------------------------------------------------------------------------
        */

        $outForDelivery = (clone $shipments)
            ->where('status', 'out_for_delivery')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | DELIVERED TODAY
        |--------------------------------------------------------------------------
        |
        | Counts shipments delivered today based on
        | their latest updated_at timestamp.
        |
        | Later, delivery_events will provide more
        | precise event-based reporting.
        |
        */

        $deliveredToday = (clone $shipments)
            ->where('status', 'delivered')
            ->whereDate('updated_at', today())
            ->count();

        /*
        |--------------------------------------------------------------------------
        | DASHBOARD STATISTICS
        |--------------------------------------------------------------------------
        */

        $stats = [
            'pending_pickups' => $pendingPickups,
            'incoming_parcels' => $incomingParcels,
            'sorting_queue' => $sortingQueue,
            'active_riders' => $activeRiders,
            'available_riders' => 0,
            'out_for_delivery' => $outForDelivery,
            'delivered_today' => $deliveredToday,
        ];

        return view('logistics.dashboard', [
            'user' => $user,
            'provider' => $provider,
            'application' => $application,
            'stats' => $stats,
        ]);
    }
}
