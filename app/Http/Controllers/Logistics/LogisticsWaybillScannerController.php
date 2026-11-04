<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsWaybillScannerController extends Controller
{
    private function providerId(): int
    {
        $providerId = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless(
            $providerId,
            403,
            'Your Logistics account is not approved.'
        );

        return (int) $providerId;
    }

    public function index()
    {
        $this->providerId();

        return view('logistics.scanner.index');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'tracking_code' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $trackingCode = trim($data['tracking_code']);

        if ($trackingCode === '') {
            throw ValidationException::withMessages([
                'tracking_code' => 'A tracking code is required.',
            ]);
        }

        $providerId = $this->providerId();

        $shipment = DB::table('shipments')
            ->where('logistics_provider_id', $providerId)
            ->where('tracking_code', $trackingCode)
            ->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'No parcel matching this tracking code was found for your Logistics account.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Waybill verified successfully.',
            'shipment' => [
                'id' => $shipment->id,
                'tracking_code' => $shipment->tracking_code,
                'status' => $shipment->status ?? 'Unknown',
                'rider_id' => $shipment->rider_id ?? null,
                'seller_order_id' => $shipment->seller_order_id ?? null,
                'created_at' => $shipment->created_at ?? null,
                'updated_at' => $shipment->updated_at ?? null,
            ],
        ]);
    }
}
