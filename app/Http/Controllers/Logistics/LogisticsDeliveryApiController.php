<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Notifications\MarketplaceAlert;
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

        abort_unless($id, 403, 'Approved logistics account required.');

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
                    // Keep the database key machine-readable.  The previous
                    // API returned `RDR-001` here, which forced the browser to
                    // reverse-engineer the key before it could save an
                    // assignment.
                    'id' => (int) $r->id,
                    'code' => 'RDR-' . str_pad(
                        $r->id, 3, '0', STR_PAD_LEFT
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

    public function assignment()
    {
        $providerId = $this->providerId();

        $shipments = $this->baseShipments($providerId)
            ->where(function ($query) {
                $query->where('ls.stage', 'sorted')
                    ->orWhereIn('s.status', [
                        'assigned',
                        'out_for_delivery',
                        'in_transit',
                    ]);
            })
            ->whereIn('s.status', [
                'unassigned',
                'assigned',
                'picked_up',
                'in_transit',
                'out_for_delivery',
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
                        ? (int) $shipment->rider_id
                        : null,
                    'status' => in_array(
                        $shipment->status,
                        ['out_for_delivery', 'in_transit'],
                        true
                    )
                        ? 'Dispatched'
                        : ($shipment->rider_id
                            ? 'Assigned'
                            : 'Awaiting Assignment'),
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

            abort_unless($rider, 422, 'Active rider not found.');

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

    public function updateStatus(Request $request, int $id)
    {
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

        DB::transaction(function () use ($id, $providerId, $data, &$sellerOrderId) {
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

            if ($data['status'] === 'Delivered') {
                DB::table('shipments')
                    ->where('id', $id)
                    ->update([
                        'status' => 'delivered',
                        'updated_at' => now(),
                    ]);

                DB::table('seller_orders')
                    ->where('id', $shipment->seller_order_id)
                    ->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                        'updated_at' => now(),
                    ]);
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

            $note = $data['remarks'] ?? 'Status updated by Logistics.';

            $this->event(
                $shipment,
                $next,
                $note
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
                    'note' => $note,
                    'metadata' => json_encode([
                        'shipment_id' => $shipment->id,
                        'tracking_code' => $shipment->tracking_code,
                        'delivery_status' => $next,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        if ($sellerOrderId) {
            $sellerOrder = SellerOrder::with([
                'order.buyer',
                'seller.user',
                'shipment.provider',
            ])->find($sellerOrderId);

            if ($sellerOrder) {
                $label = $data['status'];
                $tracking = $sellerOrder->shipment?->tracking_code;
                $message = "{$sellerOrder->order->reference}: {$label}."
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
            'message' => 'Delivery status updated.',
        ]);
    }

    private function event(
        object $shipment,
        string $status,
        string $note
    ): void {
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
    }
}
