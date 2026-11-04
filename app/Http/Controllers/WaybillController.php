<?php

namespace App\Http\Controllers;

use App\Models\SellerOrder;
use Illuminate\Http\Request;

class WaybillController extends Controller
{
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

}
