<?php

namespace App\Services;

use App\Models\DeliveryEvent;
use App\Models\LogisticsProvider;
use App\Models\OrderTimeline;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class FulfillmentService
{
    public function requestPickup(SellerOrder $sellerOrder, LogisticsProvider $provider, User $actor): Shipment
    {
        if ($provider->status !== 'approved') {
            throw ValidationException::withMessages([
                'logistics_provider_id' => 'Choose an approved logistics provider.',
            ]);
        }

        if ($sellerOrder->shipment()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This order already has a shipment. Logistics will update the delivery milestones.',
            ]);
        }

        $sellerOrder->update(['logistics_provider_id' => $provider->id]);

        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => $provider->id,
            'tracking_code' => $this->trackingCode(),
            'status' => 'unassigned',
            'fee_minor' => (int) ($sellerOrder->shipping_fee_minor ?? 0),
            'cod_amount_minor' => (int) $sellerOrder->subtotal_minor + (int) $sellerOrder->shipping_fee_minor,
        ]);

        DeliveryEvent::create([
            'shipment_id' => $shipment->id,
            'status' => 'ready_for_pickup',
            'attempt' => 1,
            'user_id' => $actor->id,
            'note' => 'Seller packed the order and requested courier pickup.',
            'occurred_at' => now(),
        ]);

        OrderTimeline::create([
            'order_id' => $sellerOrder->order_id,
            'seller_order_id' => $sellerOrder->id,
            'actor_id' => $actor->id,
            'status' => 'ready_to_ship',
            'event' => 'pickup_requested',
            'note' => "Pickup requested from {$provider->name}. Tracking: {$shipment->tracking_code}.",
            'metadata' => [
                'shipment_id' => $shipment->id,
                'tracking_code' => $shipment->tracking_code,
                'logistics_provider_id' => $provider->id,
            ],
        ]);

        return $shipment;
    }

    public function confirmPickup(SellerOrder $sellerOrder, LogisticsProvider $provider, User $actor): Shipment
    {
        $shipment = $sellerOrder->shipment()->lockForUpdate()->first();

        if (! $shipment || $sellerOrder->logistics_provider_id !== $provider->id || $shipment->logistics_provider_id !== $provider->id) {
            abort(403, 'This shipment is not assigned to your logistics provider.');
        }

        if ($sellerOrder->status !== 'ready_to_ship' || $shipment->status !== 'unassigned') {
            throw ValidationException::withMessages([
                'pickup' => 'Only a ready-for-pickup order with an unassigned shipment can be collected.',
            ]);
        }

        $shipment->update(['status' => 'picked_up']);
        $sellerOrder->update(['status' => 'shipped']);

        DeliveryEvent::create([
            'shipment_id' => $shipment->id,
            'status' => 'picked_up',
            'attempt' => 1,
            'user_id' => $actor->id,
            'note' => 'Parcel collected from the seller by logistics.',
            'occurred_at' => now(),
        ]);

        OrderTimeline::create([
            'order_id' => $sellerOrder->order_id,
            'seller_order_id' => $sellerOrder->id,
            'actor_id' => $actor->id,
            'status' => 'shipped',
            'event' => 'logistics_pickup_confirmed',
            'note' => "Collected by {$provider->name}. Tracking: {$shipment->tracking_code}.",
            'metadata' => [
                'shipment_id' => $shipment->id,
                'tracking_code' => $shipment->tracking_code,
                'logistics_provider_id' => $provider->id,
            ],
        ]);

        return $shipment;
    }

    private function trackingCode(): string
    {
        do {
            $code = 'LM-TRK-'.Str::upper(Str::random(10));
        } while (Shipment::where('tracking_code', $code)->exists());

        return $code;
    }
}
