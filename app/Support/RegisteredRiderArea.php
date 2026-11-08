<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisteredRiderArea
{
    public static function location(mixed $value): array
    {
        if (is_object($value)) {
            $value = (array) $value;
        } elseif (is_string($value)) {
            $value = json_decode($value, true);
        }

        $value = is_array($value) ? $value : [];

        $municipality = $value['city']
            ?? $value['municipality']
            ?? '';

        $province = $value['province'] ?? '';

        return [
            'municipality' => is_string($municipality)
                ? trim($municipality)
                : '',
            'province' => is_string($province)
                ? trim($province)
                : '',
        ];
    }

    private static function normal(string $value): string
    {
        return mb_strtolower(
            preg_replace('/\s+/u', ' ', trim($value))
        );
    }

    public static function hasArea(mixed $value): bool
    {
        $area = self::location($value);

        return $area['municipality'] !== ''
            && $area['province'] !== '';
    }

    public static function label(mixed $value): string
    {
        $area = self::location($value);

        return self::hasArea($value)
            ? $area['municipality'].', '.$area['province']
            : 'Area not registered';
    }

    public static function matches(
        mixed $rider,
        mixed $destination
    ): bool {
        if (
            ! self::hasArea($rider)
            || ! self::hasArea($destination)
        ) {
            return false;
        }

        $riderArea = self::location($rider);
        $parcelArea = self::location($destination);

        return self::normal($riderArea['municipality'])
                === self::normal($parcelArea['municipality'])
            && self::normal($riderArea['province'])
                === self::normal($parcelArea['province']);
    }

    public static function ensureShipmentMatches(
        object $rider,
        object $shipment
    ): void {
        if (! self::hasArea($rider)) {
            throw ValidationException::withMessages([
                'rider_id' => 'The rider’s registered municipality and province are missing. They must be supplied by rider registration.',
            ]);
        }

        $destination = DB::table('seller_orders as so')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->where('so.id', $shipment->seller_order_id)
            ->where(
                'so.logistics_provider_id',
                $rider->logistics_provider_id
            )
            ->value('o.shipping_address');

        if (! self::matches($rider, $destination)) {
            throw ValidationException::withMessages([
                'rider_id' => 'This parcel is outside the rider’s registered municipality and province.',
            ]);
        }
    }
}
