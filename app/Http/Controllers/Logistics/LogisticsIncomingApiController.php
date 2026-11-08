<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsProvider;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\FulfillmentService;
use App\Support\LogisticsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsIncomingApiController extends Controller
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

    public function index(): JsonResponse
    {
        $providerId = $this->providerId();

        $shipments = DB::table('shipments as s')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'so.seller_id')
            ->leftJoin('logistics_sorting as ls', 'ls.shipment_id', '=', 's.id')
            ->where('s.logistics_provider_id', $providerId)
            ->whereIn('s.status', [
                'unassigned', 'picked_up', 'in_transit', 'assigned'
            ])
            ->select(
                's.id',
                's.tracking_code',
                's.status',
                's.created_at',
                'seller.name as seller_name',
                'o.shipping_address',
                'ls.stage as sorting_stage',
                'ls.received_at',
                'ls.condition',
                'ls.remarks',
                'ls.verified_at'
            )
            ->orderByDesc('s.created_at')
            ->get();

        $records = $shipments->map(function ($shipment) {
            $address = json_decode(
                $shipment->shipping_address ?? '{}',
                true
            );

            if (!is_array($address)) {
                $address = [];
            }

            $received = $shipment->sorting_stage !== null;

            return [
                'id' => $shipment->id,
                'tracking' => $shipment->tracking_code,
                'seller' => $shipment->seller_name ?? 'Seller',
                'recipient' => $address['recipient']
                    ?? $address['recipient_name']
                    ?? $address['name']
                    ?? 'Recipient',
                'contact' => $address['phone'] ?? '',
                'destination' => implode(', ', array_filter([
                    $address['barangay'] ?? '',
                    $address['city'] ?? '',
                    $address['province'] ?? '',
                ])),
                'type' => 'Standard',
                'weight' => '—',
                'expected' => substr($shipment->created_at, 0, 10),
                'status' => !$received
                    ? 'Awaiting Arrival'
                    : ($shipment->sorting_stage === 'discrepancy'
                        ? 'Discrepancy'
                        : ($shipment->sorting_stage === 'sorted'
                            ? 'Ready for Sorting'
                            : 'Received')),
                'notes' => '',
                'receivedAt' => $shipment->received_at
                    ? substr($shipment->received_at, 0, 10)
                    : '',
                'condition' => $shipment->condition ?? '',
                'remarks' => $shipment->remarks ?? '',
                'verifiedAt' => $shipment->verified_at,
            ];
        });

        return response()->json([
            'success' => true,
            'parcels' => $records,
        ]);
    }

    public function receive(
        Request $request,
        int $id,
        FulfillmentService $fulfillment
    ): JsonResponse
    {
        $data = $request->validate([
            'condition' => [
                'nullable',
                'string',
                'max:120'
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:500'
            ],
        ]);

        $providerId = $this->providerId();

        DB::transaction(function () use (
            $id,
            $providerId,
            $data,
            $fulfillment
        ): void {
            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404, 'Shipment not found.');

            abort_unless(
                in_array($shipment->status, [
                    'unassigned', 'picked_up', 'in_transit'
                ], true),
                422,
                'Shipment is not waiting to be received at this hub.'
            );

            $existing = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->first();

            abort_if($existing, 422, 'Shipment already received.');

            if ($shipment->status === 'unassigned') {
                $sellerOrder = SellerOrder::query()
                    ->whereKey($shipment->seller_order_id)
                    ->where('logistics_provider_id', $providerId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $provider = LogisticsProvider::query()
                    ->findOrFail($providerId);
                $actor = Auth::user();

                abort_unless($actor instanceof User, 403);

                $fulfillment->confirmPickup(
                    $sellerOrder,
                    $provider,
                    $actor
                );
            }

            DB::table('logistics_sorting')->insert([
                'shipment_id' => $id,
                'logistics_provider_id' => $providerId,
                'stage' => 'received',
                'condition' => $data['condition'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('delivery_events')->insert([
                'shipment_id' => $id,
                'status' => 'sorting_received',
                'attempt' => max(1, (int) $shipment->attempts),
                'user_id' => Auth::id(),
                'note' => trim(
                    'Received at sorting center. '
                    . ($data['condition'] ?? '')
                    . ' '
                    . ($data['remarks'] ?? '')
                ),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // NEW: Save a real Logistics notification.
            LogisticsNotification::create(
                $providerId,
                'parcel_received',
                'Parcel Received',
                'Waybill ' . $shipment->tracking_code
                    . ' was received at your sorting center.',
                '/logistics/incoming-parcels'
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Parcel received successfully.',
        ]);
    }

    public function verify(Request $request, int $id)
    {
        $data = $request->validate([
            'condition' => [
                'required',
                'string',
                'in:Good condition,Damaged packaging,Incorrect parcel information,Other concern',
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $providerId = $this->providerId();

        DB::transaction(function () use ($id, $providerId, $data): void {
            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($sorting, 404, 'Received parcel not found.');
            abort_unless(
                $sorting->stage === 'received',
                422,
                'Only a received parcel can be verified.'
            );

            $now = now();
            $hasDiscrepancy = $data['condition'] !== 'Good condition';

            DB::table('logistics_sorting')
                ->where('id', $sorting->id)
                ->update([
                    'condition' => $data['condition'],
                    'remarks' => $data['remarks'] ?? null,
                    'stage' => $hasDiscrepancy ? 'discrepancy' : 'received',
                    'verified_at' => $now,
                    'verified_by' => Auth::id(),
                    'updated_at' => $now,
                ]);

            DB::table('delivery_events')->insert([
                'shipment_id' => $id,
                'status' => $hasDiscrepancy
                    ? 'parcel_discrepancy_reported'
                    : 'parcel_condition_verified',
                'attempt' => 1,
                'user_id' => Auth::id(),
                'note' => trim($data['condition'].' '.($data['remarks'] ?? '')),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => $data['condition'] === 'Good condition'
                ? 'Parcel verification saved. It is ready for waybill verification and sorting.'
                : 'Parcel discrepancy recorded for review.',
        ]);
    }

    public function review(Request $request, int $id)
    {
        $data = $request->validate([
            'condition' => [
                'required',
                'string',
                'in:Good condition,Damaged packaging,Incorrect parcel information,Other concern',
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $providerId = $this->providerId();

        DB::transaction(function () use ($id, $providerId, $data): void {
            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($sorting, 404, 'Parcel discrepancy not found.');
            abort_unless(
                $sorting->stage === 'discrepancy',
                422,
                'This parcel has no discrepancy to review.'
            );

            $now = now();
            $hasDiscrepancy = $data['condition'] !== 'Good condition';

            DB::table('logistics_sorting')
                ->where('id', $sorting->id)
                ->update([
                    'condition' => $data['condition'],
                    'remarks' => $data['remarks'] ?? null,
                    'stage' => $hasDiscrepancy ? 'discrepancy' : 'received',
                    'discrepancy_reviewed_at' => $now,
                    'discrepancy_reviewed_by' => Auth::id(),
                    'updated_at' => $now,
                ]);

            DB::table('delivery_events')->insert([
                'shipment_id' => $id,
                'status' => $hasDiscrepancy
                    ? 'parcel_discrepancy_reviewed'
                    : 'parcel_discrepancy_cleared',
                'attempt' => 1,
                'user_id' => Auth::id(),
                'note' => trim($data['condition'].' '.($data['remarks'] ?? '')),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => $data['condition'] === 'Good condition'
                ? 'Discrepancy cleared. Parcel is ready for waybill verification and sorting.'
                : 'Discrepancy review saved. The parcel remains on hold.',
        ]);
    }
}
