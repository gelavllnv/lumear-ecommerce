<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    public function index()
    {
        $providerId = $this->providerId();

        $shipments = DB::table('shipments as s')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'so.seller_id')
            ->leftJoin('logistics_sorting as ls', 'ls.shipment_id', '=', 's.id')
            ->where('s.logistics_provider_id', $providerId)
            ->whereIn('s.status', [
                'picked_up', 'in_transit', 'assigned'
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
                'ls.verified_at',
                'ls.discrepancy_reviewed_at'
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
                        : ($shipment->sorting_stage === 'sorted' || $shipment->verified_at
                            ? 'Ready for Sorting'
                            : 'Received')),
                'notes' => '',
                'receivedAt' => $shipment->received_at
                    ? substr($shipment->received_at, 0, 10)
                    : '',
                'condition' => $shipment->condition ?? '',
                'remarks' => $shipment->remarks ?? '',
            ];
        });

        return response()->json([
            'success' => true,
            'parcels' => $records,
        ]);
    }

    public function receive(Request $request, int $id)
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

        DB::transaction(function () use ($id, $providerId, $data) {
            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404, 'Shipment not found.');

            abort_unless(
                in_array($shipment->status, [
                    'picked_up', 'in_transit'
                ], true),
                422,
                'Shipment must be picked up before receiving.'
            );

            $existing = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->first();

            abort_if($existing, 422, 'Shipment already received.');

            DB::table('logistics_sorting')->insert([
                'shipment_id' => $id,
                'logistics_provider_id' => $providerId,
                'stage' => 'received',
                'condition' => $data['condition'] ?? 'Good condition',
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
        });

        return response()->json([
            'success' => true,
            'message' => 'Parcel received successfully.',
        ]);
    }

    public function verify(Request $request, int $id)
    {
        return $this->recordInspection($request, $id, false);
    }

    public function review(Request $request, int $id)
    {
        return $this->recordInspection($request, $id, true);
    }

    private function recordInspection(Request $request, int $id, bool $review): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'condition' => [
                'required',
                Rule::in([
                    'Good condition',
                    'Damaged packaging',
                    'Incorrect parcel information',
                    'Other concern',
                ]),
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $providerId = $this->providerId();
        $hasDiscrepancy = $data['condition'] !== 'Good condition';

        DB::transaction(function () use (
            $id,
            $providerId,
            $data,
            $review,
            $hasDiscrepancy
        ): void {
            $record = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($record, 404, 'Received parcel not found.');

            if ($review) {
                abort_unless(
                    $record->stage === 'discrepancy',
                    422,
                    'Only a flagged parcel can be reviewed.'
                );
            } else {
                abort_unless(
                    $record->stage === 'received' && !$record->verified_at,
                    422,
                    'This parcel has already been verified or is not available for verification.'
                );
            }

            $now = now();
            $update = [
                'condition' => $data['condition'],
                'remarks' => $data['remarks'] ?? null,
                'stage' => $hasDiscrepancy ? 'discrepancy' : 'received',
                'updated_at' => $now,
            ];

            if ($review) {
                $update['discrepancy_reviewed_at'] = $now;
                $update['discrepancy_reviewed_by'] = Auth::id();
            }

            if (!$hasDiscrepancy) {
                $update['verified_at'] = $now;
                $update['verified_by'] = Auth::id();
            }

            DB::table('logistics_sorting')
                ->where('id', $record->id)
                ->update($update);

            DB::table('delivery_events')->insert([
                'shipment_id' => $id,
                'status' => $hasDiscrepancy
                    ? ($review ? 'sorting_discrepancy_reviewed' : 'sorting_discrepancy_reported')
                    : 'sorting_verified',
                'attempt' => 1,
                'user_id' => Auth::id(),
                'note' => trim(
                    ($review ? 'Discrepancy review: ' : 'Parcel verification: ')
                    . $data['condition']
                    . '. '
                    . ($data['remarks'] ?? '')
                ),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => $hasDiscrepancy
                ? 'Discrepancy recorded for review.'
                : 'Parcel verified and ready for sorting.',
        ]);
    }
}
