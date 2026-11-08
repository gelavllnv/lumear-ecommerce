<?php

namespace App\Services;

use App\Http\Controllers\Logistics\LogisticsDeliveryApiController;
use App\Support\RegisteredRiderArea;
use App\Support\RiderJobType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SemiAutomaticDeliveryService
{
    public const CAPACITY = 5;

    public const ACTIVE_STATUSES = [
        'assigned',
        'picked_up',
        'in_transit',
        'out_for_delivery',
    ];

    public function __construct(
        private RiderRemittanceService $remittances
    ) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'assignments' => $message,
        ]);
    }

    private function provider(int $providerId): object
    {
        $query = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->where('status', 'approved');

        if (DB::connection()->transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $provider = $query->first();

        abort_unless($provider, 403);

        return $provider;
    }

    public function queue(int $providerId)
    {
        return DB::table('shipments as s')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->join('logistics_sorting as ls', 'ls.shipment_id', '=', 's.id')
            ->where('s.logistics_provider_id', $providerId)
            ->where('so.logistics_provider_id', $providerId)
            ->where('ls.logistics_provider_id', $providerId)
            ->whereNull('s.rider_id')
            ->whereIn('s.status', [
                'unassigned',
                'picked_up',
                'in_transit',
            ])
            ->whereNotIn('so.status', [
                'delivered',
                'cancelled',
            ])
            ->where('ls.stage', 'sorted')
            ->whereNotNull('ls.waybill_verified_at')
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('rider_tasks as t')
                    ->whereColumn('t.shipment_id', 's.id')
                    ->where(function ($active) {
                        $active
                            ->whereNotIn(
                                't.status',
                                RiderTaskAssignmentService::TERMINAL_STATUSES
                            )
                            ->orWhereNull('t.status');
                    });
            })
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('logistics_hub_transfers as h')
                    ->whereColumn('h.shipment_id', 's.id')
                    ->where('h.status', 'in_transit');
            })
            ->select(
                's.id',
                's.tracking_code',
                'o.shipping_address',
                'ls.sorted_at'
            )
            ->orderBy('ls.sorted_at')
            ->orderBy('s.id');
    }

    public function workload(int $riderId): int
    {
        $query = DB::table('shipments')
            ->where('rider_id', $riderId)
            ->whereIn('status', self::ACTIVE_STATUSES);

        return DB::connection()->transactionLevel() > 0
            ? $query
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id'])
                ->count()
            : $query->count();
    }

    public function riders(int $providerId): array
    {
        return DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->where('r.job_type', RiderJobType::DELIVERY)
            ->select('r.*', 'u.name')
            ->orderBy('r.id')
            ->get()
            ->filter(
                fn ($rider) => $rider->job_type === RiderJobType::DELIVERY
                    && RiderJobType::isAvailable($rider)
                    && RegisteredRiderArea::hasArea($rider)
                    && $this->remittances->canAcceptNewAssignment(
                        (int) $rider->id,
                        $providerId
                    )
            )
            ->map(fn ($rider) => [
                'id' => (int) $rider->id,
                'name' => $rider->name,
                'municipality' => $rider->municipality,
                'province' => $rider->province,
                'area' => RegisteredRiderArea::label($rider),
                'load' => $this->workload((int) $rider->id),
                'capacity' => self::CAPACITY,
            ])
            ->values()
            ->all();
    }

    private function normal(mixed $value): string
    {
        return is_string($value)
            ? mb_strtolower(
                preg_replace('/\s+/u', ' ', trim($value))
            )
            : '';
    }

    public function destination(mixed $value): array
    {
        $address = is_array($value)
            ? $value
            : json_decode($value ?: '{}', true);

        $address = is_array($address) ? $address : [];

        return [
            'city' => is_string($address['city'] ?? null)
                ? $address['city']
                : ($address['municipality'] ?? ''),
            'province' => $address['province'] ?? '',
        ];
    }

    private function serves(
        object $provider,
        object $parcel
    ): bool {
        $address = $this->destination(
            $parcel->shipping_address
        );

        return $this->normal($provider->municipality_name) !== ''
            && $this->normal($provider->province_name) !== ''
            && $this->normal($address['city'])
                === $this->normal($provider->municipality_name)
            && $this->normal($address['province'])
                === $this->normal($provider->province_name);
    }

    public function preview(
        int $providerId,
        array $ids
    ): array {
        $provider = $this->provider($providerId);
        $riders = $this->riders($providerId);
        $projected = array_column($riders, 'load', 'id');

        $parcels = $this->queue($providerId)
            ->whereIn('s.id', $ids)
            ->get();

        $rows = [];
        $skipped = [];

        foreach (
            array_diff($ids, $parcels->pluck('id')->all()) as $id
        ) {
            $skipped[] = [
                'tracking' => 'Parcel #'.$id,
                'reason' => 'No longer eligible. Refresh the queue.',
            ];
        }

        foreach ($parcels as $parcel) {
            if (! $this->serves($provider, $parcel)) {
                $skipped[] = [
                    'tracking' => $parcel->tracking_code,
                    'reason' => 'Destination is missing or outside this hub’s service area. Review it for hub transfer.',
                ];

                continue;
            }

            $matching = array_values(
                array_filter(
                    $riders,
                    fn ($rider) => RegisteredRiderArea::matches(
                        $rider,
                        $parcel->shipping_address
                    )
                )
            );

            $eligible = array_values(
                array_filter(
                    $matching,
                    fn ($rider) => $projected[$rider['id']] < self::CAPACITY
                )
            );

            usort(
                $eligible,
                fn ($a, $b) => (
                    $projected[$a['id']]
                    <=> $projected[$b['id']]
                )
                    ?: ($a['id'] <=> $b['id'])
            );

            $riderId = $eligible[0]['id'] ?? null;

            if ($riderId !== null) {
                $projected[$riderId]++;
            }

            $rows[] = [
                'id' => (int) $parcel->id,
                'tracking' => $parcel->tracking_code,
                'rider_id' => $riderId,
                'eligible_rider_ids' => array_column($matching, 'id'),
                'reason' => $riderId
                    ? 'Registered area matches; lowest projected workload.'
                    : 'No matching rider area with free capacity.',
            ];
        }

        return compact('rows', 'skipped', 'riders');
    }

    public function confirm(
        int $providerId,
        array $rows
    ): int {
        $rows = array_values(
            array_filter(
                $rows,
                fn ($row) => ! empty($row['rider_id'])
            )
        );

        if (! $rows || count($rows) > 50) {
            $this->fail(
                'Select at least one assignment, up to 50 parcels.'
            );
        }

        $ids = array_map(
            fn ($row) => (int) $row['shipment_id'],
            $rows
        );

        if (count(array_unique($ids)) !== count($ids)) {
            $this->fail('A parcel can appear only once.');
        }

        return DB::transaction(function () use (
            $providerId,
            $rows,
            $ids
        ) {
            $provider = $this->provider($providerId);

            $riderIds = array_values(
                array_unique(
                    array_map(
                        fn ($row) => (int) $row['rider_id'],
                        $rows
                    )
                )
            );

            sort($riderIds);

            $lockedRiders = [];

            foreach ($riderIds as $id) {
                $rider = DB::table('riders')
                    ->where('id', $id)
                    ->where('logistics_provider_id', $providerId)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $rider
                    || $rider->job_type !== RiderJobType::DELIVERY
                    || ! RiderJobType::isAvailable($rider)
                    || ! $this->remittances->canAcceptNewAssignment(
                        $id,
                        $providerId
                    )
                ) {
                    $this->fail(
                        'A rider is unavailable or restricted. Generate a new preview.'
                    );
                }

                $lockedRiders[$id] = $rider;
            }

            $locked = DB::table('shipments')
                ->where('logistics_provider_id', $providerId)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (
                $locked->count() !== count($ids)
                || $locked->contains(
                    fn ($parcel) => $parcel->rider_id !== null
                )
            ) {
                $this->fail(
                    'A parcel changed after the preview. Generate a new preview.'
                );
            }

            DB::table('seller_orders')
                ->whereIn('id', $locked->pluck('seller_order_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            DB::table('logistics_sorting')
                ->whereIn('shipment_id', $ids)
                ->orderBy('shipment_id')
                ->lockForUpdate()
                ->get();

            $activeTasks = DB::table('rider_tasks')
                ->whereIn('shipment_id', $ids)
                ->lockForUpdate()
                ->get();

            $transfers = DB::table('logistics_hub_transfers')
                ->whereIn('shipment_id', $ids)
                ->where('status', 'in_transit')
                ->lockForUpdate()
                ->get();

            if (
                $transfers->isNotEmpty()
                || $activeTasks->contains(
                    fn ($task) => ! in_array(
                        $task->status,
                        RiderTaskAssignmentService::TERMINAL_STATUSES,
                        true
                    )
                )
            ) {
                $this->fail(
                    'A parcel has an active task or transfer. Generate a new preview.'
                );
            }

            $parcels = $this->queue($providerId)
                ->whereIn('s.id', $ids)
                ->lockForUpdate()
                ->get();

            if ($parcels->count() !== count($ids)) {
                $this->fail(
                    'A parcel changed after the preview. Nothing was saved; generate a new preview.'
                );
            }

            foreach ($parcels as $parcel) {
                if (! $this->serves($provider, $parcel)) {
                    $this->fail(
                        'A parcel is outside this hub’s service area.'
                    );
                }
            }

            $byId = $parcels->keyBy('id');

            foreach ($rows as $row) {
                $rider = $lockedRiders[
                    (int) $row['rider_id']
                ];

                $parcel = $byId[
                    (int) $row['shipment_id']
                ];

                if (
                    ! RegisteredRiderArea::matches(
                        $rider,
                        $parcel->shipping_address
                    )
                ) {
                    $this->fail(
                        'A parcel does not match the selected rider’s registered municipality and province.'
                    );
                }
            }

            foreach ($riderIds as $id) {
                $incoming = count(
                    array_filter(
                        $rows,
                        fn ($row) => (int) $row['rider_id'] === $id
                    )
                );

                if (
                    $this->workload($id) + $incoming
                    > self::CAPACITY
                ) {
                    $this->fail(
                        'A rider would exceed five active parcels. Adjust the choices or generate a new preview.'
                    );
                }
            }

            foreach ($riderIds as $id) {
                $shipmentIds = array_map(
                    fn ($row) => (int) $row['shipment_id'],
                    array_values(
                        array_filter(
                            $rows,
                            fn ($row) => (int) $row['rider_id'] === $id
                        )
                    )
                );

                $request = Request::create(
                    '/logistics/api/delivery/assign',
                    'POST',
                    [
                        'rider_id' => $id,
                        'shipment_ids' => $shipmentIds,
                        'notes' => 'Semi-automatic assignment reviewed and confirmed by logistics.',
                    ]
                );

                app(LogisticsDeliveryApiController::class)
                    ->assign(
                        $request,
                        $this->remittances
                    );
            }

            return count($rows);
        }, 3);
    }
}
