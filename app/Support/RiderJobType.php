<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class RiderJobType
{
    public const DELIVERY = 'Delivery';

    public const SELLER_PICKUP = 'Seller Pickup';

    public const HUB_TRANSFER = 'Long-distance Handover';

    public static function values(): array
    {
        return [
            self::DELIVERY,
            self::SELLER_PICKUP,
            self::HUB_TRANSFER,
        ];
    }

    public static function validateSelected(mixed $value): string
    {
        if (
            !is_string($value)
            || !in_array($value, self::values(), true)
        ) {
            throw ValidationException::withMessages([
                'job_type' =>
                    'The rider must select a valid job type in the rider app.',
            ]);
        }

        return $value;
    }

    public static function ensureMatches(
        object $rider,
        string $jobType
    ): void {
        self::validateSelected($jobType);

        if (($rider->job_type ?? null) !== $jobType) {
            throw ValidationException::withMessages([
                'rider_id' =>
                    'Select a rider registered for ' . $jobType . '.',
            ]);
        }
    }

    public static function isAvailable(object $rider): bool
    {
        return (bool) $rider->is_active
            && in_array(
                $rider->management_status,
                ['Available', 'approved'],
                true
            );
    }
}
