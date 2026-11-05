<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\LogisticsWalletService;
use App\Services\RiderPayrollService;
use App\Services\RiderRemittanceService;
use App\Support\LogisticsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsDeliveryApiController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LOGISTICS PROVIDER
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | DESTINATION FORMATTER
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | RIDER LIST
    |--------------------------------------------------------------------------
    */

    private function riders(
        int $providerId,
        RiderRemittanceService $remittanceService
    ) {
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
                'r.management_status',
                'u.name'
            )
            ->orderBy('u.name')
            ->get()
            ->map(function ($r) use (
                $serviceArea,
                $providerId,
                $remittanceService
            ) {
                $available = $remittanceService
                    ->canAcceptNewAssignment(
                        (int) $r->id,
                        $providerId
                    );

                return [
                    'id' => 'RDR-' . str_pad(
                        $r->id,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'name' => $r->name,
                    'area' => $serviceArea,
                    'vehicle' => $r->vehicle_type ?: 'Rider',
                    'capacity' => 5,
                    'available' => $available,
                    'remittanceRestricted' => DB::table(
                        'rider_remittance_restrictions'
                    )
                        ->where('rider_id', $r->id)
                        ->where(
                            'logistics_provider_id',
                            $providerId
                        )
                        ->where('status', 'active')
                        ->where('blocks_new_assignments', true)
                        ->exists(),
                ];
            });
    }

    /*
    |--------------------------------------------------------------------------
    | BASE SHIPMENTS QUERY
    |--------------------------------------------------------------------------
    */

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

    public function assignment(
        RiderRemittanceService $remittanceService
    ) {
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
            'riders' => $this->riders(
                $providerId,
                $remittanceService
            ),

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

                    'seller' => $shipment->seller_name
                        ?? 'Seller',

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

    /*
    |--------------------------------------------------------------------------
    | ASSIGN SHIPMENTS TO RIDER
    |--------------------------------------------------------------------------
    */

    public function assign(
        Request $request,
        RiderRemittanceService $remittanceService
    ) {
        $data = $request->validate([
            'rider_id' => 'required|integer',
            'shipment_ids' => 'required|array|min:1|max:50',
            'shipment_ids.*' => 'integer|distinct',
            'notes' => 'nullable|string|max:500',
        ]);

        $providerId = $this->providerId();

        /*
        | Check restrictions before entering the assignment
        | transaction. The same check is repeated inside it.
        */

        if (!$remittanceService->canAcceptNewAssignment(
            (int) $data['rider_id'],
            $providerId
        )) {
            throw ValidationException::withMessages([
                'rider_id' =>
                    'This rider cannot receive new assignments. Check approval status and outstanding COD remittances.',
            ]);
        }

        DB::transaction(function () use (
            $data,
            $providerId,
            $remittanceService
        ) {
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

            if (!$remittanceService->canAcceptNewAssignment(
                (int) $rider->id,
                $providerId
            )) {
                throw ValidationException::withMessages([
                    'rider_id' =>
                        'Rider is restricted from receiving new parcels.',
                ]);
            }

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
                    !$shipment
                    || $shipment->stage !== 'sorted'
                    || !in_array(
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

                /*
                | Prevent moving an in-progress parcel
                | to a different rider.
                */

                if (
                    in_array(
                        $shipment->status,
                        ['picked_up', 'in_transit'],
                        true
                    )
                    && $shipment->rider_id
                    && (int) $shipment->rider_id !==
                        (int) $rider->id
                ) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'An in-progress parcel cannot be reassigned.',
                    ]);
                }

                if (
                    (int) $shipment->rider_id !==
                    (int) $rider->id
                ) {
                    $used++;
                }

                if ($used > 5) {
                    throw ValidationException::withMessages([
                        'rider_id' =>
                            'Rider assignment capacity exceeded.',
                    ]);
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
        }, 3);

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

        DB::transaction(function () use (
            $id,
            $providerId
        ) {
            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404);

            abort_unless(
                $shipment->status === 'assigned'
                && $shipment->rider_id,
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
        }, 3);

        return response()->json([
            'message' => 'Shipment dispatched.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY MONITORING
    |--------------------------------------------------------------------------
    */

    public function monitoring(
        RiderRemittanceService $remittanceService
    ) {
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

                $history = (
                    $events->get($shipment->id) ?? collect()
                )
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
                    )
                    && $shipment->status !== 'delivered'
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
            'riders' => $this->riders(
                $providerId,
                $remittanceService
            ),
            'deliveries' => $deliveries,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DISPLAY DELIVERY STATUS
    |--------------------------------------------------------------------------
    */

    private function displayStatus(string $status): string
    {
        return match ($status) {
            'dispatched', 'assigned' => 'Dispatched',

            'in_transit' => 'In Transit',

            'out_for_delivery' => 'Out for Delivery',

            'delivered' => 'Delivered',

            'delivery_delayed' => 'Delayed',

            'delivery_failed_attempt', 'failed' =>
                'Failed Attempt',

            default => ucwords(
                str_replace('_', ' ', $status)
            ),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE DELIVERY STATUS
    |--------------------------------------------------------------------------
    |
    | Successful delivery:
    |
    | 1. Updates shipment and seller order
    | 2. Credits Logistics commission
    | 3. Credits Rider Payroll commission
    | 4. Creates COD obligation if collection confirmed
    | 5. Records delivery event
    | 6. Creates Logistics notification
    |
    | All operations use one database transaction.
    |
    */

    public function updateStatus(
        Request $request,
        int $id,
        LogisticsWalletService $walletService,
        RiderPayrollService $payrollService,
        RiderRemittanceService $remittanceService
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
            )
            && empty(trim($data['remarks'] ?? ''))
        ) {
            throw ValidationException::withMessages([
                'remarks' =>
                    'Remarks are required for delivery exceptions.',
            ]);
        }

        $providerId = $this->providerId();

        $result = DB::transaction(function () use (
            $id,
            $providerId,
            $data,
            $walletService,
            $payrollService,
            $remittanceService
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
                )
                && $shipment->status !== 'delivered'
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

            $walletCredited = false;
            $riderCredited = false;
            $codObligationCreated = false;
            $codCollectionConfirmed = false;
            $codAmountMinor = 0;

            /*
            |--------------------------------------------------------------------------
            | SUCCESSFUL DELIVERY
            |--------------------------------------------------------------------------
            */

            if ($data['status'] === 'Delivered') {
                $rider = DB::table('riders')
                    ->where('id', $shipment->rider_id)
                    ->where(
                        'logistics_provider_id',
                        $providerId
                    )
                    ->first();

                abort_unless(
                    $rider,
                    422,
                    'Assigned rider does not belong to this Logistics provider.'
                );

                $deliveryTime = now();

                DB::table('shipments')
                    ->where('id', $id)
                    ->update([
                        'status' => 'delivered',
                        'updated_at' => $deliveryTime,
                    ]);

                DB::table('seller_orders')
                    ->where('id', $shipment->seller_order_id)
                    ->where(
                        'logistics_provider_id',
                        $providerId
                    )
                    ->update([
                        'status' => 'delivered',
                        'delivered_at' => $deliveryTime,
                        'updated_at' => $deliveryTime,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | LOGISTICS E-WALLET COMMISSION
                |--------------------------------------------------------------------------
                |
                | Existing PHP 5 commission preserved.
                |
                */

                $walletCredited = $walletService
                    ->creditDeliveredShipment($id);

                /*
                |--------------------------------------------------------------------------
                | RIDER DELIVERY COMMISSION
                |--------------------------------------------------------------------------
                |
                | Existing PHP 5 Rider Payroll credit preserved.
                |
                */

                $riderCredited = $payrollService
                    ->creditDeliveredShipment($id);

                $commissionExists = DB::table(
                    'rider_delivery_commissions'
                )
                    ->where('shipment_id', $id)
                    ->where('rider_id', $rider->id)
                    ->where(
                        'logistics_provider_id',
                        $providerId
                    )
                    ->exists();

                if (!$commissionExists) {
                    throw ValidationException::withMessages([
                        'shipment' =>
                            'Rider commission could not be recorded. Delivery update cancelled.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | COD REMITTANCE OBLIGATION
                |--------------------------------------------------------------------------
                |
                | Create only if COD collection is confirmed.
                |
                | Do not automatically set cod_collected = true.
                | The Rider App / collection workflow must
                | confirm that the buyer actually paid.
                |
                | This records money owed, not wallet funds.
                |
                */

                $codCollectionConfirmed =
                    (bool) $shipment->cod_collected;

                $codAmountMinor =
                    (int) $shipment->cod_amount_minor;

                if (
                    $codCollectionConfirmed
                    && $codAmountMinor > 0
                ) {
                    $codObligationCreated =
                        $remittanceService->createCodObligation(
                            $id,
                            \Carbon\Carbon::parse(
                                $deliveryTime
                            )
                        );
                }
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
            | DELIVERY EVENTS AND NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            $this->event(
                $shipment,
                $next,
                $data['remarks']
                    ?? 'Status updated by Logistics.'
            );

            return [
                'wallet_credited' => $walletCredited,
                'rider_credited' => $riderCredited,
                'cod_obligation_created' =>
                    $codObligationCreated,
                'cod_collection_confirmed' =>
                    $codCollectionConfirmed,
                'cod_amount_minor' => $codAmountMinor,
                'shipment_status' => $next,
            ];
        }, 3);

        /*
        |--------------------------------------------------------------------------
        | API RESPONSE
        |--------------------------------------------------------------------------
        */

        $message = 'Delivery status updated.';

        if ($result['shipment_status'] === 'delivered') {
            $message = 'Delivery completed successfully.';

            if (
                $result['wallet_credited']
                && $result['rider_credited']
            ) {
                $message =
                    'Delivery completed. PHP 5.00 credited to Logistics E-Wallet and PHP 5.00 earned by the rider.';
            }

            if ($result['cod_obligation_created']) {
                $message .=
                    ' COD remittance obligation recorded.';
            }

            if (
                $result['cod_amount_minor'] > 0
                && !$result['cod_collection_confirmed']
            ) {
                $message .=
                    ' COD collection is not yet confirmed; remittance obligation was not created.';
            }
        }

        return response()->json([
            'message' => $message,

            /*
            | Existing Logistics E-Wallet response
            */

            'commission_credited' =>
                $result['wallet_credited'],

            'commission_amount' =>
                $result['wallet_credited']
                    ? 5.00
                    : 0,

            /*
            | Existing Rider Payroll response
            */

            'rider_commission_credited' =>
                $result['rider_credited'],

            'rider_commission_amount' =>
                $result['rider_credited']
                    ? 5.00
                    : 0,

            /*
            | New COD remittance response
            */

            'cod_obligation_created' =>
                $result['cod_obligation_created'],

            'cod_collection_confirmed' =>
                $result['cod_collection_confirmed'],

            'cod_amount_minor' =>
                $result['cod_amount_minor'],

            'shipment_status' =>
                $result['shipment_status'],
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

        $notificationTitles = [
            'assigned' => 'Parcel Assigned to Rider',
            'dispatched' => 'Parcel Dispatched',
            'in_transit' => 'Parcel In Transit',
            'out_for_delivery' => 'Parcel Out for Delivery',
            'delivered' => 'Parcel Delivered',
            'delivery_delayed' => 'Delivery Delayed',
            'delivery_failed_attempt' =>
                'Failed Delivery Attempt',
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
