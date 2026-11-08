<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use App\Models\SellerOrder;
use App\Notifications\MarketplaceAlert;
use App\Services\FulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaybillController extends Controller
{
    public function __construct(private readonly FulfillmentService $fulfillment) {}

    /** Seller-facing printable waybill. */
    public function print(Request $request, SellerOrder $sellerOrder)
    {
        abort_unless(
            $sellerOrder->seller_id === $request->user()->seller?->id,
            403
        );

        $sellerOrder->load([
            'shipment.provider',
            'seller.pickupAddress',
            'seller.owner',
            'order.buyer',
            'items',
        ]);

        abort_unless(
            $sellerOrder->shipment &&
                !in_array($sellerOrder->status, ['pending', 'accepted', 'cancelled'], true),
            422,
            'Create a courier pickup request before printing a waybill.'
        );

        $shipment = $sellerOrder->shipment;
        $shipping = is_array($sellerOrder->order->shipping_address)
            ? $sellerOrder->order->shipping_address
            : [];

        return view('seller.waybill', [
            'sellerOrder' => $sellerOrder,
            'shipment' => $shipment,
            'shipping' => $shipping,
            // The value contains no address or payment details. Laravel still
            // checks the provider and shipment state before accepting a scan.
            'qrPayload' => 'LUMEAR-WAYBILL:' . $shipment->tracking_code,
        ]);
    }

    /** Logistics web page used by a warehouse scanner or device camera. */
    public function scanner()
    {
        return view('logistics.pickup.scan');
    }

    /** Confirm pickup from a printed Lumear waybill. */
    public function scan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waybill' => ['required', 'string', 'max:180'],
        ]);

        $waybillPayload = trim($data['waybill']);
        $decodedPayload = json_decode($waybillPayload, true);
        if (is_string($decodedPayload)) {
            $waybillPayload = trim($decodedPayload);
        }

        $trackingCode = strtoupper($waybillPayload);
        $trackingCode = preg_replace(
            '/^LUMEAR-WAYBILL\s*:\s*/',
            '',
            $trackingCode
        );

        if (!is_string($trackingCode) || !preg_match('/^LM-TRK-[A-Z0-9]{10}$/', $trackingCode)) {
            throw ValidationException::withMessages([
                'waybill' => 'This is not a valid Lumear waybill QR code.',
            ]);
        }

        $provider = $this->provider();

        DB::transaction(function () use ($trackingCode, $provider): void {
            $sellerOrder = SellerOrder::query()
                ->with('shipment')
                ->where('logistics_provider_id', $provider->id)
                ->whereHas('shipment', fn ($query) => $query
                    ->where('tracking_code', $trackingCode))
                ->lockForUpdate()
                ->first();

            abort_unless(
                $sellerOrder,
                404,
                'No shipment for this waybill belongs to your logistics provider.'
            );

            $this->fulfillment->confirmPickup(
                $sellerOrder,
                $provider,
                Auth::user()
            );
        });

        $sellerOrder = SellerOrder::with(['order.buyer', 'seller.user'])
            ->whereHas('shipment', fn ($query) => $query
                ->where('tracking_code', $trackingCode))
            ->firstOrFail();

        $sellerOrder->order->buyer?->notify(new MarketplaceAlert(
            'Parcel picked up',
            "Your order {$sellerOrder->order->reference} is now with logistics.",
            route('orders')
        ));
        $sellerOrder->seller?->user?->notify(new MarketplaceAlert(
            'Parcel picked up',
            "{$sellerOrder->order->reference} was confirmed by waybill scan.",
            route('seller.orders')
        ));

        return redirect()
            ->route('logistics.pickup.scan')
            ->with('status', "Pickup confirmed for {$trackingCode}.");
    }

    private function provider(): LogisticsProvider
    {
        $provider = LogisticsProvider::query()
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        abort_unless($provider, 403, 'Approved logistics account required.');

        return $provider;
    }
}
