<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Support\LogisticsNotification;
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
                'ls.received_at'
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
                    : ($shipment->sorting_stage === 'sorted'
                        ? 'Ready for Sorting'
                        : 'Received'),
                'notes' => '',
                'receivedAt' => $shipment->received_at
                    ? substr($shipment->received_at, 0, 10)
                    : '',
                'condition' => '',
                'remarks' => '',
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
}
