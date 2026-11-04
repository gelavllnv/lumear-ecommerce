<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsProvider;
use App\Models\SellerOrder;
use App\Notifications\MarketplaceAlert;
use App\Services\FulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsPickupController extends Controller
{
    public function __construct(private readonly FulfillmentService $fulfillment) {}

    /**
     * Get the approved Logistics provider belonging
     * to the currently authenticated user.
     */
    private function provider(): LogisticsProvider
    {
        $user = Auth::user();

        abort_unless($user, 401);

        $provider = LogisticsProvider::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        abort_unless(
            $provider,
            403,
            'You do not have an approved Logistics account.'
        );

        return $provider;
    }

    /**
     * Display pickup requests assigned to this provider.
     */
    public function index(Request $request)
    {
        $provider = $this->provider();

        $allowedStatuses = [
            'all',
            'ready_to_ship',
            'shipped',
            'delivered',
            'cancelled',
        ];

        $status = $request->query(
            'status',
            'ready_to_ship'
        );

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'ready_to_ship';
        }

        $search = trim(
            (string) $request->query('search', '')
        );

        $query = DB::table('seller_orders as so')
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
            ->leftJoin(
                'shipments as sh',
                'sh.seller_order_id',
                '=',
                'so.id'
            )
            ->where(
                'so.logistics_provider_id',
                $provider->id
            )
            ->select([
                'so.id',
                'so.order_id',
                'so.seller_id',
                'so.status',
                'so.shipping_fee_minor',
                'so.created_at',
                'so.updated_at',
                'o.reference',
                'o.payment_method',
                'o.shipping_address',
                'seller.name as seller_name',
                'sh.id as shipment_id',
                'sh.tracking_code',
                'sh.status as shipment_status',
            ]);

        if ($status !== 'all') {
            $query->where('so.status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'o.reference',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'seller.name',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'sh.tracking_code',
                        'like',
                        '%' . $search . '%'
                    );
            });
        }

        $pickups = $query
            ->orderByDesc('so.created_at')
            ->paginate(10)
            ->withQueryString();

        $counts = DB::table('seller_orders')
            ->where(
                'logistics_provider_id',
                $provider->id
            )
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN status = 'ready_to_ship'
                THEN 1 ELSE 0 END) as pending"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'shipped'
                THEN 1 ELSE 0 END) as shipped"
            )
            ->first();

        return view('logistics.pickup.index', [
            'provider' => $provider,
            'pickups' => $pickups,
            'status' => $status,
            'search' => $search,
            'counts' => $counts,
        ]);
    }

    public function confirm(int $id)
    {
        $provider = $this->provider();

        DB::transaction(function () use ($id, $provider): void {
            $sellerOrder = SellerOrder::with('shipment')
                ->where('id', $id)
                ->where('logistics_provider_id', $provider->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->fulfillment->confirmPickup($sellerOrder, $provider, Auth::user());
        });

        $sellerOrder = SellerOrder::with(['order.buyer', 'seller.user'])->findOrFail($id);
        $sellerOrder->order->buyer->notify(new MarketplaceAlert(
            'Parcel picked up',
            "Your order {$sellerOrder->order->reference} is now with logistics.",
            route('orders')
        ));
        $sellerOrder->seller?->user?->notify(new MarketplaceAlert(
            'Parcel picked up',
            "{$sellerOrder->order->reference} has been collected by the courier.",
            route('seller.orders')
        ));

        return redirect()
            ->route('logistics.pickup.show', $id)
            ->with('status', 'Pickup confirmed. The parcel is now available to incoming-parcel operations.');
    }
    /**
     * Show the complete details of a pickup request.
     */
    public function show(int $id)
    {
        $provider = $this->provider();

        $pickup = DB::table('seller_orders as so')
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
            ->leftJoin(
                'shipments as sh',
                'sh.seller_order_id',
                '=',
                'so.id'
            )
            ->where('so.id', $id)
            ->where(
                'so.logistics_provider_id',
                $provider->id
            )
            ->select([
                'so.*',
                'o.reference',
                'o.payment_method',
                'o.payment_status',
                'o.shipping_address',
                'seller.name as seller_name',
                'seller.email as seller_email',
                'sh.id as shipment_id',
                'sh.tracking_code',
                'sh.status as shipment_status',
                'sh.rider_id',
            ])
            ->first();

        abort_unless($pickup, 404);

        $items = DB::table('order_items')
            ->where(
                'seller_order_id',
                $pickup->id
            )
            ->get();

        return view('logistics.pickup.show', [
            'provider' => $provider,
            'pickup' => $pickup,
            'items' => $items,
        ]);
    }
}
