<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LogisticsReportsApiController extends Controller
{
    private function providerId(): int
    {
        $id = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless($id, 403, 'Approved Logistics account required.');

        return (int) $id;
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'type' => 'required|in:all,pickup,incoming,sorting,assignment,delivery,rider',
        ]);

        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'to' => 'Please select a period of 366 days or less.',
            ]);
        }

        return $data;
    }

    private function status(string $value): string
    {
        return match ($value) {
            'delivered',
            'completed',
            'sorted',
            'received',
            'approved',
            'picked_up' => 'Completed',

            'failed',
            'returned',
            'cancelled',
            'rejected',
            'suspended',
            'delivery_delayed',
            'delivery_failed_attempt' => 'Exception',

            default => 'In Progress',
        };
    }

    private function address($value): string
    {
        $data = is_array($value)
            ? $value
            : json_decode($value ?: '{}', true);

        if (!is_array($data)) {
            return 'Not specified';
        }

        return implode(', ', array_filter([
            $data['city'] ?? $data['municipality'] ?? '',
            $data['province'] ?? '',
        ])) ?: 'Not specified';
    }

    private function record(
        string $id,
        $date,
        string $module,
        string $description,
        string $assignedTo,
        string $location,
        string $status
    ): array {
        return [
            'id' => $id,
            'date' => substr((string) $date, 0, 10),
            'module' => $module,
            'description' => $description,
            'assignedTo' => $assignedTo,
            'location' => $location,
            'status' => $status,
        ];
    }

    private function records(int $providerId, array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $type = $filters['type'];

        $include = fn (string $module) =>
            $type === 'all' || $type === $module;

        $records = [];

        $provider = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();

        $providerArea = implode(', ', array_filter([
            $provider->municipality_name ?? '',
            $provider->province_name ?? '',
        ])) ?: 'Sorting Center';

        $shipmentQuery = function () use ($providerId) {
            return DB::table('shipments as s')
                ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
                ->join('orders as o', 'o.id', '=', 'so.order_id')
                ->leftJoin('riders as r', 'r.id', '=', 's.rider_id')
                ->leftJoin('users as rider_user', 'rider_user.id', '=', 'r.user_id')
                ->where('s.logistics_provider_id', $providerId)
                ->select(
                    's.id',
                    's.tracking_code',
                    's.status',
                    's.rider_id',
                    's.created_at',
                    's.updated_at',
                    'o.shipping_address',
                    'rider_user.name as rider_name'
                );
        };

        // Incoming Parcels: shipment creation/activity.
        if ($include('incoming')) {
            foreach (
                $shipmentQuery()
                    ->whereDate('s.created_at', '>=', $from)
                    ->whereDate('s.created_at', '<=', $to)
                    ->get() as $shipment
            ) {
                $records[] = $this->record(
                    'INC-' . $shipment->id,
                    $shipment->created_at,
                    'incoming',
                    'Shipment registered: ' . $shipment->tracking_code,
                    'Logistics Staff',
                    $this->address($shipment->shipping_address),
                    $this->status($shipment->status)
                );
            }
        }

        // Parcel Sorting: actual sorting records.
        if (
            $include('sorting') &&
            Schema::hasTable('logistics_sorting')
        ) {
            $sorting = DB::table('logistics_sorting as ls')
                ->join('shipments as s', 's.id', '=', 'ls.shipment_id')
                ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
                ->join('orders as o', 'o.id', '=', 'so.order_id')
                ->where('ls.logistics_provider_id', $providerId)
                ->where('s.logistics_provider_id', $providerId)
                ->whereDate('ls.updated_at', '>=', $from)
                ->whereDate('ls.updated_at', '<=', $to)
                ->select(
                    'ls.id',
                    'ls.stage',
                    'ls.updated_at',
                    's.tracking_code',
                    'o.shipping_address'
                )
                ->get();

            foreach ($sorting as $item) {
                $records[] = $this->record(
                    'SRT-' . $item->id,
                    $item->updated_at,
                    'sorting',
                    'Parcel ' . $item->stage . ': ' . $item->tracking_code,
                    'Sorting Staff',
                    $this->address($item->shipping_address),
                    $this->status($item->stage)
                );
            }
        }

        // Delivery Assignment: assignment events.
        if ($include('assignment')) {
            $assignments = DB::table('delivery_events as e')
                ->join('shipments as s', 's.id', '=', 'e.shipment_id')
                ->leftJoin('riders as r', 'r.id', '=', 's.rider_id')
                ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
                ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
                ->join('orders as o', 'o.id', '=', 'so.order_id')
                ->where('s.logistics_provider_id', $providerId)
                ->where('e.status', 'assigned')
                ->whereDate('e.occurred_at', '>=', $from)
                ->whereDate('e.occurred_at', '<=', $to)
                ->select(
                    'e.id',
                    'e.occurred_at',
                    's.tracking_code',
                    'u.name as rider_name',
                    'o.shipping_address'
                )
                ->get();

            foreach ($assignments as $item) {
                $records[] = $this->record(
                    'ASN-' . $item->id,
                    $item->occurred_at,
                    'assignment',
                    'Rider assigned to ' . $item->tracking_code,
                    $item->rider_name ?? 'Assigned Rider',
                    $this->address($item->shipping_address),
                    'Completed'
                );
            }
        }

        // Delivery Monitoring: actual delivery status events.
        if ($include('delivery')) {
            $events = DB::table('delivery_events as e')
                ->join('shipments as s', 's.id', '=', 'e.shipment_id')
                ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
                ->join('orders as o', 'o.id', '=', 'so.order_id')
                ->leftJoin('riders as r', 'r.id', '=', 's.rider_id')
                ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
                ->where('s.logistics_provider_id', $providerId)
                ->whereNotIn('e.status', [
                    'assigned',
                    'sorting_completed',
                ])
                ->whereDate('e.occurred_at', '>=', $from)
                ->whereDate('e.occurred_at', '<=', $to)
                ->select(
                    'e.id',
                    'e.status',
                    'e.occurred_at',
                    'e.note',
                    's.tracking_code',
                    'u.name as rider_name',
                    'o.shipping_address'
                )
                ->get();

            foreach ($events as $item) {
                $records[] = $this->record(
                    'DLV-' . $item->id,
                    $item->occurred_at,
                    'delivery',
                    ucfirst(str_replace('_', ' ', $item->status))
                        . ': ' . $item->tracking_code,
                    $item->rider_name ?? 'Logistics Staff',
                    $this->address($item->shipping_address),
                    $this->status($item->status)
                );
            }
        }

        // Rider Performance: deliveries completed by riders.
        if ($include('rider')) {
            $deliveries = $shipmentQuery()
                ->where('s.status', 'delivered')
                ->whereNotNull('s.rider_id')
                ->whereDate('s.updated_at', '>=', $from)
                ->whereDate('s.updated_at', '<=', $to)
                ->get();

            foreach ($deliveries as $shipment) {
                $records[] = $this->record(
                    'RDR-' . $shipment->id,
                    $shipment->updated_at,
                    'rider',
                    'Completed delivery: ' . $shipment->tracking_code,
                    $shipment->rider_name ?? 'Rider',
                    $this->address($shipment->shipping_address),
                    'Completed'
                );
            }
        }

        // Pickup Requests: only if an existing compatible table is present.
        // Do not invent pickup records or assume an unknown table schema.
        if (
            $include('pickup') &&
            Schema::hasTable('pickup_requests') &&
            Schema::hasColumns('pickup_requests', [
                'id',
                'logistics_provider_id',
                'status',
                'created_at',
            ])
        ) {
            $pickups = DB::table('pickup_requests')
                ->where('logistics_provider_id', $providerId)
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->get();

            foreach ($pickups as $pickup) {
                $records[] = $this->record(
                    'PUP-' . $pickup->id,
                    $pickup->created_at,
                    'pickup',
                    'Pickup request: ' . $pickup->status,
                    'Logistics Staff',
                    $providerArea,
                    $this->status($pickup->status)
                );
            }
        }

        usort($records, fn ($a, $b) =>
            strcmp($b['date'], $a['date']) ?:
            strcmp($a['id'], $b['id'])
        );

        return $records;
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $providerId = $this->providerId();

        $records = $this->records($providerId, $filters);

        return response()->json([
            'success' => true,
            'records' => $records,
            'summary' => [
                'total' => count($records),
                'completed' => count(array_filter(
                    $records,
                    fn ($r) => $r['status'] === 'Completed'
                )),
                'progress' => count(array_filter(
                    $records,
                    fn ($r) => $r['status'] === 'In Progress'
                )),
                'exceptions' => count(array_filter(
                    $records,
                    fn ($r) => $r['status'] === 'Exception'
                )),
            ],
        ]);
    }
}
