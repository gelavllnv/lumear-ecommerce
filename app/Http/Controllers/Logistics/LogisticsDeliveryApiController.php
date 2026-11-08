<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\OrderTimeline;
use App\Models\User;
use App\Notifications\MarketplaceAlert;
use App\Services\LogisticsWalletService;
use App\Services\RiderPayrollService;
use App\Services\RiderRemittanceService;
use App\Services\RiderTaskAssignmentService;
use App\Support\RiderHubEligibility;
use App\Support\LogisticsNotification;
use App\Support\RiderJobType;
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

    private function riders(
        int $providerId,
        RiderRemittanceService $remittanceService
    ) {
        $provider = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();
        abort_unless($provider, 403, 'Approved logistics hub required.');

        return DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->where('r.job_type', RiderJobType::DELIVERY)
            ->select(
                'r.id',
                'r.vehicle_type',
                'r.job_type',
                'r.is_active',
                'r.management_status',
                'r.municipality',
                'r.province',
                'r.barangay',
                'u.name'
            )
            ->orderBy('u.name')
            ->get()
            ->filter(
                fn ($rider) => $rider->job_type === RiderJobType::DELIVERY
                    && RiderHubEligibility::matches($rider, $provider)
            )
            ->values()
            ->map(function ($rider) use ($providerId, $remittanceService) {
                $available = $remittanceService
                    ->canAcceptNewAssignment(
                        (int) $rider->id,
                        $providerId
                    );

                return [
                    'id' => 'RDR-' . str_pad(
                        (string) $rider->id,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'name' => $rider->name,
                    'area' => implode(', ', array_filter([
                        $rider->barangay ?? '',
                        $rider->municipality ?? '',
                        $rider->province ?? '',
                    ])),
                    'vehicle' => $rider->vehicle_type ?: 'Rider',
                    'capacity' => 5,
                    'available' => $available,
                    'remittanceRestricted' => DB::table(
                        'rider_remittance_restrictions'
                    )
                        ->where('rider_id', $rider->id)
                        ->where('logistics_provider_id', $providerId)
                        ->where('status', 'active')
                        ->where('blocks_new_assignments', true)
                        ->exists(),
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
                    'seller' => $shipment->seller_name ?? 'Seller',
                    'destination' => $this->destination($address),
                    'type' => 'Standard',
                    'weight' => '—',
                    'batch' => $shipment->batch_id
                        ? 'BATCH-' . str_pad(
                            (string) $shipment->batch_id,
                            3,
                            '0',
                            STR_PAD_LEFT
                        )
                        : '—',
                    'riderId' => $shipment->rider_id
                        ? 'RDR-' . str_pad(
                            (string) $shipment->rider_id,
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
        $provider = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();

        abort_unless($provider, 403, 'Approved logistics hub required.');

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
            $provider,
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

            RiderHubEligibility::ensureMatches($rider, $provider);

            RiderJobType::ensureMatches(
                $rider,
                RiderJobType::DELIVERY
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

                if (
                    in_array(
                        $shipment->status,
                        ['picked_up', 'in_transit'],
                        true
                    )
                    && $shipment->rider_id
                    && (int) $shipment->rider_id !== (int) $rider->id
                ) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'An in-progress parcel cannot be reassigned.',
                    ]);
                }

                if ((int) $shipment->rider_id !== (int) $rider->id) {
                    $used++;
                }

                if ($used > 5) {
                    throw ValidationException::withMessages([
                        'rider_id' =>
                            'Rider assignment capacity exceeded.',
                    ]);
                }

                app(RiderTaskAssignmentService::class)->record(
                    $shipment,
                    $rider,
                    RiderJobType::DELIVERY,
                    null,
                    $data['notes'] ?? null
                );

                DB::table('shipments')
                    ->where('id', $id)
                    ->update([
                        'rider_id' => $rider->id,
                        'status' => 'assigned',
                        'updated_at' => now(),
                    ]);

                $buyerId = DB::table('seller_orders as so')
                    ->join('orders as o', 'o.id', '=', 'so.order_id')
                    ->where('so.id', $shipment->seller_order_id)
                    ->value('o.buyer_id');
                if ($buyerId) {
                    $conversation = Conversation::firstOrCreate([
                        'buyer_id' => $buyerId,
                        'rider_id' => $rider->id,
                        'shipment_id' => $shipment->id,
                    ]);
                    if (!$conversation->wasRecentlyCreated && $conversation->rider_deleted_at) {
                        $conversation->update([
                            'rider_deleted_at' => null,
                            'updated_at' => now(),
                        ]);
                    }
                    if ($conversation->wasRecentlyCreated) {
                        User::query()->find($buyerId)?->notify(new MarketplaceAlert(
                            'Your delivery rider has been assigned',
                            'You can now message your rider about parcel ' . $shipment->tracking_code . '.',
                            route('messages.show', $conversation),
                        ));
                    }
                }

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

        $deliveries = $shipments->map(function ($shipment) use ($events) {
            $address = json_decode(
                $shipment->shipping_address ?: '{}',
                true
            ) ?: [];

            $history = ($events->get($shipment->id) ?? collect())
                ->map(fn ($event) => [
                    'status' => $this->displayStatus($event->status),
                    'time' => substr(
                        (string) $event->occurred_at,
                        0,
                        16
                    ),
                    'note' => $event->note ?? '',
                ])
                ->values()
                ->all();

            $latest = end($history);

            $status = $this->displayStatus($shipment->status);

            if (
                in_array(
                    $latest['status'] ?? '',
                    [
                        'Dispatched',
                        'Delay Reported',
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
                    (string) $shipment->rider_id,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'date' => substr(
                    (string) $shipment->created_at,
                    0,
                    10
                ),
                'status' => $status,
                'remarks' => $latest['note'] ?? '',
                'history' => $history,
            ];
        });

        return response()->json([
            'riders' => $this->riders(
                $providerId,
                $remittanceService
            ),
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
            'delivery_delayed', 'delivery_rescheduled' => 'Delayed',
            'delivery_delay_reported' => 'Delay Reported',
            'delivery_failed_attempt', 'failed' => 'Failed Attempt',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

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
            'rescheduled_for' => [
                'required_if:status,Delayed',
                'nullable',
                'date',
                'after:now',
            ],
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

            $current = $this->displayStatus($shipment->status);

            if (
                in_array(
                    $lastEvent?->status,
                    [
                        'dispatched',
                        'delivery_delayed',
                        'delivery_rescheduled',
                        'delivery_delay_reported',
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
                    'Delayed',
                    'Failed Attempt',
                ],
                'Delay Reported' => [
                    'Delayed',
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
                'Delayed' => 'delivery_rescheduled',
                'Failed Attempt' => 'delivery_failed_attempt',
            };

            $eventNote = $data['remarks']
                ?? 'Status updated by Logistics.';

            if ($data['status'] === 'Delayed') {
                $rescheduledAt = \Illuminate\Support\Carbon::parse(
                    $data['rescheduled_for']
                );
                $rescheduledLabel = $rescheduledAt->format(
                    'M j, Y g:i A'
                );
                $eventNote = 'Delivery rescheduled to '
                    . $rescheduledLabel
                    . '. Reason: '
                    . trim((string) $data['remarks']);

                $buyer = DB::table('seller_orders as so')
                    ->join('orders as o', 'o.id', '=', 'so.order_id')
                    ->where('so.id', $shipment->seller_order_id)
                    ->first(['o.id as order_id', 'o.buyer_id']);

                if ($buyer) {
                    $buyerMessage = 'Your delivery is delayed. '
                        . 'The new expected delivery is '
                        . $rescheduledLabel
                        . '. Your current rider will continue the delivery.';

                    OrderTimeline::create([
                        'order_id' => $buyer->order_id,
                        'seller_order_id' => $shipment->seller_order_id,
                        'actor_id' => Auth::id(),
                        'status' => 'shipped',
                        'event' => 'delivery_rescheduled',
                        'note' => $buyerMessage,
                    ]);

                    User::query()
                        ->find($buyer->buyer_id)?->notify(
                            new MarketplaceAlert(
                                'Delivery rescheduled',
                                $buyerMessage,
                                route('orders'),
                            )
                        );
                }

                $rider = DB::table('riders')
                    ->where('id', $shipment->rider_id)
                    ->where('logistics_provider_id', $providerId)
                    ->first();

                if ($rider) {
                    User::query()
                        ->find($rider->user_id)?->notify(
                            new MarketplaceAlert(
                                'Delivery rescheduled',
                                'Parcel ' . $shipment->tracking_code
                                    . ' is rescheduled for '
                                    . $rescheduledLabel
                                    . '. You remain assigned to this delivery.',
                                null,
                            )
                        );
                }
            }

            $walletCredited = false;
            $riderCredited = false;
            $codObligationCreated = false;
            $codCollectionConfirmed = false;
            $codAmountMinor = 0;

            if ($data['status'] === 'Delivered') {
                $rider = DB::table('riders')
                    ->where('id', $shipment->rider_id)
                    ->where('logistics_provider_id', $providerId)
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
                    ->where('logistics_provider_id', $providerId)
                    ->update([
                        'status' => 'delivered',
                        'delivered_at' => $deliveryTime,
                        'updated_at' => $deliveryTime,
                    ]);

                $walletCredited = $walletService
                    ->creditDeliveredShipment($id);

                $riderCredited = $payrollService
                    ->creditDeliveredShipment($id);

                $commissionExists = DB::table(
                    'rider_delivery_commissions'
                )
                    ->where('shipment_id', $id)
                    ->where('rider_id', $rider->id)
                    ->where('logistics_provider_id', $providerId)
                    ->exists();

                if (!$commissionExists) {
                    throw ValidationException::withMessages([
                        'shipment' =>
                            'Rider commission could not be recorded. Delivery update cancelled.',
                    ]);
                }

                // COD collection must be confirmed by the collection workflow.
                // Completing delivery does not automatically confirm payment.
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
                            \Carbon\Carbon::parse($deliveryTime)
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

            $this->event(
                $shipment,
                $next,
                $eventNote
            );

            if ($next === 'delivered') {
                DB::table('rider_tasks')
                    ->where('shipment_id', $shipment->id)
                    ->where('logistics_provider_id', $providerId)
                    ->where('rider_id', $shipment->rider_id)
                    ->where('job_type', RiderJobType::DELIVERY)
                    ->whereNotIn(
                        'status',
                        RiderTaskAssignmentService::TERMINAL_STATUSES
                    )
                    ->update([
                        'status' => 'completed',
                        'updated_at' => now(),
                    ]);
            }

            return [
                'wallet_credited' => $walletCredited,
                'rider_credited' => $riderCredited,
                'cod_obligation_created' => $codObligationCreated,
                'cod_collection_confirmed' => $codCollectionConfirmed,
                'cod_amount_minor' => $codAmountMinor,
                'shipment_status' => $next,
            ];
        }, 3);

        $message = 'Delivery status updated.';

        if ($result['shipment_status'] === 'delivery_rescheduled') {
            $message = 'Delivery rescheduled. The buyer and rider were notified.';
        }

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
            'commission_credited' => $result['wallet_credited'],
            'commission_amount' => $result['wallet_credited']
                ? 5.00
                : 0,
            'rider_commission_credited' => $result['rider_credited'],
            'rider_commission_amount' => $result['rider_credited']
                ? 5.00
                : 0,
            'cod_obligation_created' =>
                $result['cod_obligation_created'],
            'cod_collection_confirmed' =>
                $result['cod_collection_confirmed'],
            'cod_amount_minor' => $result['cod_amount_minor'],
            'shipment_status' => $result['shipment_status'],
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
            'attempt' => $status === 'delivery_rescheduled'
                ? max(
                    max(1, (int) ($shipment->attempts ?? 0)),
                    (int) DB::table('delivery_events')
                        ->where('shipment_id', $shipment->id)
                        ->where('status', $status)
                        ->max('attempt') + 1,
                )
                : max(1, (int) ($shipment->attempts ?? 0)),
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
            'delivery_rescheduled' => 'Delivery Rescheduled',
            'delivery_delay_reported' => 'Rider Reported a Delivery Delay',
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
