<?php

namespace App\Services;

class RiderJobPolicy
{
    public function jobsFor(string $vehicle): array
    {
        return match ($vehicle) {
            'Motorcycle', 'Tricycle' => ['Delivery'],
            'Van' => ['Delivery', 'Seller Pickup'],
            'Truck' => ['Delivery', 'Long-distance Handover'],
            default => [],
        };
    }

    public function canDeliver(object $rider): bool
    {
        return $rider->job_type === 'Delivery'
            && in_array('Delivery', $this->jobsFor($rider->vehicle_type), true);
    }
}
