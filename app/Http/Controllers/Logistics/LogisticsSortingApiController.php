<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Support\LogisticsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsSortingApiController extends Controller
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
            'Approved Logistics account required.'
        );

        return (int) $id;
    }

    private function destination($address): string
    {
        if (is_string($address)) {
            $address = json_decode($address, true);
        }

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
    | SORTING DASHBOARD DATA
    |--------------------------------------------------------------------------
    |
    | Only verified parcels appear in sorting.
    |
    */

    public function index()
    {
        $providerId = $this->providerId();

        $batches = DB::table('logistics_sorting_batches')
            ->where('logistics_provider_id', $providerId)
            ->orderBy('id')
            ->get()
            ->map(fn ($batch) => [
                'id' => 'BATCH-' . str_pad(
                    $batch->id,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'name' => $batch->name,
                'destination' => $batch->destination,
                'notes' => $batch->notes ?? '',
                'created' => substr($batch->created_at, 0, 10),
            ]);

        $shipments = DB::table('logistics_sorting as ls')
            ->join(
                'shipments as s',
                's.id',
                '=',
                'ls.shipment_id'
            )
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
            ->where('ls.logistics_provider_id', $providerId)
            ->where('s.logistics_provider_id', $providerId)
            ->whereNotNull('ls.waybill_verified_at')
            ->whereIn('ls.stage', [
                'received',
                'sorted',
            ])
            ->select(
                's.id',
                's.tracking_code',
                'o.shipping_address',
                'seller.name as seller_name',
                'ls.stage',
                'ls.batch_id',
                'ls.waybill_verified_at',
                'ls.waybill_verification_method'
            )
            ->orderByDesc('ls.id')
            ->get();

        $parcels = $shipments->map(function ($shipment) {
            $address = json_decode(
                $shipment->shipping_address ?? '{}',
                true
            );

            if (!is_array($address)) {
                $address = [];
            }

            return [
                'id' => $shipment->id,
                'tracking' => $shipment->tracking_code,
                'recipient' => $address['recipient']
                    ?? $address['recipient_name']
                    ?? $address['name']
                    ?? 'Recipient',
                'seller' => $shipment->seller_name
                    ?? 'Seller',
                'destination' => $this->destination(
                    $address
                ),
                'type' => 'Standard',
                'weight' => '—',
                'status' => $shipment->stage === 'sorted'
                    ? 'Sorted'
                    : (
                        $shipment->batch_id
                            ? 'In Progress'
                            : 'Unsorted'
                    ),
                'batchId' => $shipment->batch_id
                    ? 'BATCH-' . str_pad(
                        $shipment->batch_id,
                        3,
                        '0',
                        STR_PAD_LEFT
                    )
                    : null,
                'waybillVerified' => true,
                'waybillVerifiedAt' =>
                    $shipment->waybill_verified_at,
                'verificationMethod' =>
                    $shipment->waybill_verification_method,
            ];
        });

        return response()->json([
            'success' => true,
            'parcels' => $parcels,
            'batches' => $batches,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE SORTING BATCH
    |--------------------------------------------------------------------------
    */

    public function createBatch(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'destination' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $providerId = $this->providerId();

        $id = DB::table(
            'logistics_sorting_batches'
        )->insertGetId([
            'logistics_provider_id' => $providerId,
            'name' => $data['name'],
            'destination' => $data['destination'],
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sorting batch created.',
            'id' => $id,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGN VERIFIED PARCELS TO BATCH
    |--------------------------------------------------------------------------
    */

    public function assign(Request $request)
    {
        $data = $request->validate([
            'batch_id' => 'required|integer',
            'shipment_ids' =>
                'required|array|min:1|max:100',
            'shipment_ids.*' =>
                'required|integer|distinct',
        ]);

        $providerId = $this->providerId();

        DB::transaction(function () use (
            $data,
            $providerId
        ) {
            $batch = DB::table(
                'logistics_sorting_batches'
            )
                ->where('id', $data['batch_id'])
                ->where('logistics_provider_id', $providerId)
                ->first();

            abort_unless(
                $batch,
                404,
                'Sorting batch not found.'
            );

            foreach ($data['shipment_ids'] as $shipmentId) {
                $shipment = DB::table('shipments as s')
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
                    ->where('s.id', $shipmentId)
                    ->where(
                        's.logistics_provider_id',
                        $providerId
                    )
                    ->select(
                        's.id',
                        'o.shipping_address'
                    )
                    ->first();

                if (!$shipment) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'A shipment does not belong to your Logistics account.',
                    ]);
                }

                if (
                    $this->destination(
                        $shipment->shipping_address
                    ) !== $batch->destination
                ) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'Parcel destination does not match the batch.',
                    ]);
                }

                $record = DB::table('logistics_sorting')
                    ->where('shipment_id', $shipmentId)
                    ->where(
                        'logistics_provider_id',
                        $providerId
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$record) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'Receive the parcel in Incoming Parcels first.',
                    ]);
                }

                if (!$record->waybill_verified_at) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'Waybill verification is required before sorting. Scan the QR code or enter the written waybill code.',
                    ]);
                }

                if (
                    $record->stage !== 'received'
                    || $record->batch_id
                ) {
                    throw ValidationException::withMessages([
                        'shipment_ids' =>
                            'A parcel is not available for batch assignment.',
                    ]);
                }

                DB::table('logistics_sorting')
                    ->where('id', $record->id)
                    ->update([
                        'batch_id' => $batch->id,
                        'updated_at' => now(),
                    ]);
            }
        }, 3);

        return response()->json([
            'success' => true,
            'message' =>
                'Verified parcels assigned to sorting batch.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETE PARCEL SORTING
    |--------------------------------------------------------------------------
    */

    public function complete(int $id)
    {
        $providerId = $this->providerId();

        DB::transaction(function () use (
            $id,
            $providerId
        ) {
            $record = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->where(
                    'logistics_provider_id',
                    $providerId
                )
                ->lockForUpdate()
                ->first();

            abort_unless(
                $record,
                404,
                'Parcel not found.'
            );

            abort_unless(
                $record->waybill_verified_at !== null,
                422,
                'Waybill verification is required before sorting.'
            );

            abort_unless(
                $record->stage === 'received'
                && $record->batch_id,
                422,
                'Parcel must be assigned to a batch first.'
            );

            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where(
                    'logistics_provider_id',
                    $providerId
                )
                ->lockForUpdate()
                ->first();

            abort_unless(
                $shipment,
                404,
                'Shipment not found.'
            );

            DB::table('logistics_sorting')
                ->where('id', $record->id)
                ->update([
                    'stage' => 'sorted',
                    'sorted_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('delivery_events')->insert([
                'shipment_id' => $id,
                'status' => 'sorting_completed',
                'attempt' => max(
                    1,
                    (int) $shipment->attempts
                ),
                'user_id' => Auth::id(),
                'note' =>
                    'Verified parcel sorted and ready for delivery assignment.',
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            LogisticsNotification::create(
                $providerId,
                'parcel_sorted',
                'Parcel Sorting Completed',
                'Waybill ' . $shipment->tracking_code
                    . ' has completed sorting.',
                '/logistics/parcel-sorting'
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Parcel marked as sorted.',
        ]);
    }
}
