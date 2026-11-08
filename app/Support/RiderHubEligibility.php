<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class RiderHubEligibility
{
    public static function matches(object $rider, object $hub): bool
    {
        $riderMunicipality = self::normalize($rider->municipality ?? null);
        $hubMunicipality = self::normalize($hub->municipality_name ?? null);
        $riderProvince = self::normalize($rider->province ?? null);
        $hubProvince = self::normalize($hub->province_name ?? null);

        return $riderMunicipality !== ''
            && $hubMunicipality !== ''
            && $riderProvince !== ''
            && $hubProvince !== ''
            && $riderMunicipality === $hubMunicipality
            && $riderProvince === $hubProvince;
    }

    public static function ensureMatches(object $rider, object $hub): void
    {
        if (! self::matches($rider, $hub)) {
            throw ValidationException::withMessages([
                'rider_id' => 'The rider’s registered municipality and province must match this hub.',
            ]);
        }
    }

    private static function normalize(mixed $location): string
    {
        return mb_strtolower(trim((string) $location));
    }
}
