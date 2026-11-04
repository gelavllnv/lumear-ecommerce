<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Notifications\MarketplaceAlert;
use App\Services\LogisticsWalletService;
use App\Support\LogisticsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsDeliveryApiController extends Controller
{
    private function providerId(): int
    {
        $id = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless(
            $id,
            403,
            'Approved logistics account required.'
        );

        return (int) $id;
    }

    private function destination($value): string
    {
        $address = is_array($value)
            ? $value
            : json_decode($value ?: '{}', true);

        if (!is_array($address)) {
            return '';
        }

        return implode(', ', array_filter([
            $address['city'] ?? '',
            $address['province'] ?? '',
        ]));
    }

    private function riders(int $providerId)
    {
        $provider = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();

        $serviceArea = implode(', ', array_filter([
            $provider->municipality_name ?? '',
            $provider->province_name ?? '',
        ]));

        return DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->select(
                'r.id',
                'r.vehicle_type',
                'r.is_active',
                'u.name'
            )
            ->orderBy('u.name')
            ->get()
            ->map(function ($r) use ($serviceArea) {
                return [
                    'id' => (int) $r->id,
                    'code' => 'RDR-' . str_pad(
                        $r->id,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'name' => $r->name,
                    'area' => $serviceArea,
                    'vehicle' => $r->vehicle_type ?: 'Rider',
                    'capacity' => 5,
                    'available' => (bool) $r->is_active,
                ];
            });
    }

    private function baseShipments(int $providerId)
    {
        return DB::table('shipments as s')
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
            ->leftJoin(
                'users as seller',
                'seller.id',
                '=',
                'so.seller_id'
            )
            ->leftJoin(
                'logistics_sorting as ls',
                'ls.shipment_id',
                '=',
                's.id'
            )
            ->where('s.logistics_provider_id', $providerId)
            ->select(
                's.id',
                's.tracking_code',
                's.rider_id',
                's.status',
                's.created_at',
                's.attempts',
                'o.shipping_address',
                'seller.name as seller_name',
                'ls.stage as sorting_stage',
                'ls.batch_id'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function assignment()
    {
        $providerId = $this->providerId();

        $shipments = $this->baseShipments($providerId)
            // A completed sorting record is the source of truth for whether a
            // parcel can enter delivery assignment. Do not hide it merely
            // because older workflow records use a legacy shipment status.
            ->where(function ($query) {
                $query->where('ls.stage', 'sorted')
                    ->orWhere(function ($assignedOrDispatched) {
                        $assignedOrDispatched
                            ->whereNotNull('s.rider_id')
                            ->whereIn('s.status', [
                                'assigned',
                                'in_transit',
                                'out_for_delivery',
                            ]);
                    });
            })
            ->whereNotIn('s.status', [
                'delivered',
                'failed',
                'returned',
                'cancelled',
            ])
            ->orderByDesc('s.id')
            ->get();

        return response()->json([
            'riders' => $this->riders($providerId),

            'parcels' => $shipments->map(function ($shipment) {
                $address = json_decode(
                    $shipment->shipping_address ?: '{}',
                    true
                ) ?: [];

                return [
                    'id' => $shipment->id,
                    'tracking' => $shipment->tracking_code,
                    'recipient' => $address['recipient']
                        ?? $address['recipient_name']
                        ?? $address['name']
                        ?? 'Recipient',
                    'contact' => $address['phone'] ?? '',
                    'seller' => $shipment->seller_name ?? 'Seller',
                    'destination' => $this->destination($address),
                    'type' => 'Standard',
                    'weight' => '—',
                    'batch' => $shipment->batch_id
                        ? 'BATCH-' . str_pad(
                            $shipment->batch_id,
                            3,
                            '0',
                            STR_PAD_LEFT
                        )
                        : '—',
                    'riderId' => $shipment->rider_id
                        ? 'RDR-' . str_pad(
                            $shipment->rider_id,
                            3,
                            '0',
                            STR_PAD_LEFT
                        )
                        : null,
                    'status' => in_array(
                        $shipment->status,
                        ['out_for_delivery', 'in_transit'],
                        true
                    )
                        ? 'Dispatched'
                        : (
                            $shipment->rider_id
                                ? 'Assigned'
                                : 'Awaiting Assignment'
                        ),
                    'notes' => '',
                ];
            }),
        ]);
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'rider_id' => 'required|integer',
            'shipment_ids' => 'required|array|min:1|max:50',
            'shipment_ids.*' => 'integer|distinct',
            'notes' => 'nullable|string|max:500',
        ]);

        $providerId = $this->providerId();

        DB::transaction(function () use ($data, $providerId) {
            $rider = DB::table('riders')
                ->where('id', $data['rider_id'])
                ->where('logistics_provider_id', $providerId)
                ->where('is_active', 1)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $rider,
                422,
                'Active rider not found.'
            );

            $used = DB::table('shipments')
                ->where('rider_id', $rider->id)
                ->where('logistics_provider_id', $providerId)
                ->where('status', 'assigned')
                ->count();

            foreach ($data['shipment_ids'] as $id) {
                $shipment = DB::table('shipments as s')
                    ->leftJoin(
                        'logistics_sorting as ls',
                        'ls.shipment_id',
                        '=',
                        's.id'
                    )
                    ->where('s.id', $id)
                    ->where('s.logistics_provider_id', $providerId)
                    ->select('s.*', 'ls.stage')
                    ->lockForUpdate()
                    ->first();

                if (
                    !$shipment ||
                    $shipment->stage !== 'sorted' ||
                    !in_array(
                        $shipment->status,
                        [
                            'unassigned',
                            'shipped',
                            'assigned',
                            'picked_up',
                            'in_transit',
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'A parcel is unavailable or not sorted.',
                    ]);
                }

                if ((int) $shipment->rider_id !== (int) $rider->id) {
                    $used++;
                }

                DB::table('shipments')
                    ->where('id', $id)
                    ->update([
                        'rider_id' => $rider->id,
                        'status' => 'assigned',
                        'updated_at' => now(),
                    ]);

                $this->event(
                    $shipment,
                    'assigned',
                    $data['notes'] ?? 'Assigned to rider.'
                );
            }

            if ($used > 5) {
                throw ValidationException::withMessages([
                    'rider_id' =>
                        'Rider assignment capacity exceeded.',
                ]);
            }
        });

        return response()->json([
            'message' => 'Assignments saved successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DISPATCH SHIPMENT
    |--------------------------------------------------------------------------
    */

    public function dispatch(int $id)
    {
        $providerId = $this->providerId();

        DB::transaction(function () use ($id, $providerId) {
            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404);

            abort_unless(
                $shipment->status === 'assigned' &&
                $shipment->rider_id,
                422,
                'Shipment must be assigned first.'
            );

            DB::table('shipments')
                ->where('id', $id)
                ->update([
                    'status' => 'in_transit',
                    'updated_at' => now(),
                ]);

            $this->event(
                $shipment,
                'dispatched',
                'Parcel dispatched to rider.'
            );
        });

        return response()->json([
            'message' => 'Shipment dispatched.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY MONITORING
    |--------------------------------------------------------------------------
    */

    public function monitoring()
    {
        $providerId = $this->providerId();

        $shipments = $this->baseShipments($providerId)
            ->whereNotNull('s.rider_id')
            ->whereIn('s.status', [
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed',
                'returned',
            ])
            ->orderByDesc('s.id')
            ->get();

        $ids = $shipments->pluck('id')->all();

        $events = empty($ids)
            ? collect()
            : DB::table('delivery_events')
                ->whereIn('shipment_id', $ids)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get()
                ->groupBy('shipment_id');

        $deliveries = $shipments->map(
            function ($shipment) use ($events) {
                $address = json_decode(
                    $shipment->shipping_address ?: '{}',
                    true
                ) ?: [];

                $history = ($events->get($shipment->id) ?? collect())
                    ->map(fn ($event) => [
                        'status' => $this->displayStatus(
                            $event->status
                        ),
                        'time' => substr(
                            $event->occurred_at,
                            0,
                            16
                        ),
                        'note' => $event->note ?? '',
                    ])
                    ->values()
                    ->all();

                $latest = end($history);

                $status = $this->displayStatus(
                    $shipment->status
                );

                if (
                    in_array(
                        $latest['status'] ?? '',
                        [
                            'Dispatched',
                            'Delayed',
                            'Failed Attempt',
                        ],
                        true
                    ) &&
                    $shipment->status !== 'delivered'
                ) {
                    $status = $latest['status'];
                }

                return [
                    'id' => $shipment->id,
                    'tracking' => $shipment->tracking_code,
                    'recipient' => $address['recipient']
                        ?? $address['recipient_name']
                        ?? $address['name']
                        ?? 'Recipient',
                    'destination' => $this->destination($address),
                    'riderId' => 'RDR-' . str_pad(
                        $shipment->rider_id,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'date' => substr(
                        $shipment->created_at,
                        0,
                        10
                    ),
                    'status' => $status,
                    'remarks' => $latest['note'] ?? '',
                    'history' => $history,
                ];
            }
        );

        return response()->json([
            'riders' => $this->riders($providerId),
            'deliveries' => $deliveries,
        ]);
    }

    private function displayStatus(string $status): string
    {
        return match ($status) {
            'dispatched', 'assigned' => 'Dispatched',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'delivery_delayed' => 'Delayed',
            'delivery_failed_attempt', 'failed' => 'Failed Attempt',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE DELIVERY STATUS
    |--------------------------------------------------------------------------
    |
    | NEW:
    | Successful delivery credits the Logistics E-Wallet.
    |
    | The delivery update, wallet credit, delivery event,
    | and notification are processed in one DB transaction.
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        int $id,
        LogisticsWalletService $walletService
    ) {
        $data = $request->validate([
            'status' =>
                'required|in:In Transit,Out for Delivery,Delivered,Delayed,Failed Attempt',
            'remarks' => 'nullable|string|max:500',
        ]);

        if (
            in_array(
                $data['status'],
                ['Delayed', 'Failed Attempt'],
                true
            ) &&
            empty(trim($data['remarks'] ?? ''))
        ) {
            throw ValidationException::withMessages([
                'remarks' =>
                    'Remarks are required for delivery exceptions.',
            ]);
        }

        $providerId = $this->providerId();
        $sellerOrderId = null;

        $commissionCredited = DB::transaction(
            function () use (
                $id,
                $providerId,
                $data,
                $walletService,
                &$sellerOrderId
            ) {
                $shipment = DB::table('shipments')
                    ->where('id', $id)
                    ->where('logistics_provider_id', $providerId)
                    ->whereNotNull('rider_id')
                    ->lockForUpdate()
                    ->first();

                abort_unless($shipment, 404);

                $lastEvent = DB::table('delivery_events')
                    ->where('shipment_id', $id)
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
                    ->first();

                $current = $this->displayStatus(
                    $shipment->status
                );

                if (
                    in_array(
                        $lastEvent?->status,
                        [
                            'dispatched',
                            'delivery_delayed',
                            'delivery_failed_attempt',
                        ],
                        true
                    ) &&
                    $shipment->status !== 'delivered'
                ) {
                    $current = $this->displayStatus(
                        $lastEvent->status
                    );
                }

                $allowed = [
                    'Dispatched' => [
                        'In Transit',
                        'Delayed',
                        'Failed Attempt',
                    ],
                    'In Transit' => [
                        'Out for Delivery',
                        'Delayed',
                        'Failed Attempt',
                    ],
                    'Out for Delivery' => [
                        'Delivered',
                        'Delayed',
                        'Failed Attempt',
                    ],
                    'Delayed' => [
                        'In Transit',
                        'Out for Delivery',
                        'Failed Attempt',
                    ],
                    'Failed Attempt' => [
                        'Out for Delivery',
                        'Delayed',
                    ],
                ];

                abort_unless(
                    in_array(
                        $data['status'],
                        $allowed[$current] ?? [],
                        true
                    ),
                    422,
                    'Invalid delivery transition.'
                );

                $next = match ($data['status']) {
                    'In Transit' => 'in_transit',
                    'Out for Delivery' => 'out_for_delivery',
                    'Delivered' => 'delivered',
                    'Delayed' => 'delivery_delayed',
                    'Failed Attempt' => 'delivery_failed_attempt',
                };

                $credited = false;

                /*
                |--------------------------------------------------------------------------
                | SUCCESSFUL DELIVERY
                |--------------------------------------------------------------------------
                */

                if ($data['status'] === 'Delivered') {
                    DB::table('shipments')
                        ->where('id', $id)
                        ->update([
                            'status' => 'delivered',
                            'updated_at' => now(),
                        ]);

                    DB::table('seller_orders')
                        ->where('id', $shipment->seller_order_id)
                        ->where(
                            'logistics_provider_id',
                            $providerId
                        )
                        ->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                            'updated_at' => now(),
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | NEW: CREDIT LOGISTICS COMMISSION
                    |--------------------------------------------------------------------------
                    |
                    | ₱50 shipping fee
                    | 10% Logistics commission
                    | ₱5 credited to E-Wallet
                    |
                    | The wallet service prevents duplicate credits.
                    | If the credit fails, the delivery transaction
                    | rolls back instead of leaving inconsistent data.
                    */

                    $credited = $walletService
                        ->creditDeliveredShipment($id);

                } elseif (
                    in_array(
                        $next,
                        ['in_transit', 'out_for_delivery'],
                        true
                    )
                ) {
                    DB::table('shipments')
                        ->where('id', $id)
                        ->update([
                            'status' => $next,
                            'updated_at' => now(),
                        ]);
                }

                /*
                |--------------------------------------------------------------------------
                | PRESERVE EXISTING DELIVERY HISTORY AND NOTIFICATIONS
                |--------------------------------------------------------------------------
                */

                $this->event(
                    $shipment,
                    $next,
                    $data['remarks'] ??
                        'Status updated by Logistics.'
                );

                $sellerOrderId = (int) $shipment->seller_order_id;
                $sellerOrder = DB::table('seller_orders')
                    ->where('id', $sellerOrderId)
                    ->first();

                if ($sellerOrder) {
                    DB::table('order_timelines')->insert([
                        'order_id' => $sellerOrder->order_id,
                        'seller_order_id' => $sellerOrder->id,
                        'actor_id' => Auth::id(),
                        'status' => $next === 'delivered' ? 'delivered' : 'shipped',
                        'event' => 'logistics_' . $next,
                        'note' => $data['remarks'] ?? 'Status updated by Logistics.',
                        'metadata' => json_encode([
                            'shipment_id' => $shipment->id,
                            'tracking_code' => $shipment->tracking_code,
                            'delivery_status' => $next,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $credited;
            }
        );

        if ($sellerOrderId) {
            $sellerOrder = SellerOrder::with([
                'order.buyer',
                'seller.user',
                'shipment.provider',
            ])->find($sellerOrderId);

            if ($sellerOrder) {
                $tracking = $sellerOrder->shipment?->tracking_code;
                $message = "{$sellerOrder->order->reference}: {$data['status']}."
                    . ($tracking ? " Tracking: {$tracking}." : '');

                $sellerOrder->order->buyer?->notify(new MarketplaceAlert(
                    'Courier update',
                    $message,
                    route('orders')
                ));
                $sellerOrder->seller?->user?->notify(new MarketplaceAlert(
                    'Delivery update',
                    $message,
                    route('seller.orders')
                ));
            }
        }

        return response()->json([
            'message' => $commissionCredited
                ? 'Delivery completed. ₱5.00 credited to Logistics E-Wallet.'
                : 'Delivery status updated.',
            'commission_credited' => $commissionCredited,
            'commission_amount' => $commissionCredited
                ? 5.00
                : 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY EVENTS AND NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    private function event(
        object $shipment,
        string $status,
        string $note
    ): void {
        // Preserve existing delivery event history.
        DB::table('delivery_events')->insert([
            'shipment_id' => $shipment->id,
            'status' => $status,
            'attempt' => max(
                1,
                (int) ($shipment->attempts ?? 0)
            ),
            'user_id' => Auth::id(),
            'note' => $note,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Preserve existing Logistics bell notifications.
        $notificationTitles = [
            'assigned' => 'Parcel Assigned to Rider',
            'dispatched' => 'Parcel Dispatched',
            'in_transit' => 'Parcel In Transit',
            'out_for_delivery' => 'Parcel Out for Delivery',
            'delivered' => 'Parcel Delivered',
            'delivery_delayed' => 'Delivery Delayed',
            'delivery_failed_attempt' => 'Failed Delivery Attempt',
        ];

        if (isset($notificationTitles[$status])) {
            LogisticsNotification::create(
                (int) $shipment->logistics_provider_id,
                'delivery_' . $status,
                $notificationTitles[$status],
                'Waybill ' . $shipment->tracking_code
                    . ' — ' . $notificationTitles[$status] . '.',
                null
            );
        }
    }
}
