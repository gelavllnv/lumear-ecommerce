<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Support\LogisticsNotification;
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
            'verification_method' => [
                'required',
                'in:qr,manual',
            ],
        ]);

        $trackingCode = trim($data['tracking_code']);
        $method = $data['verification_method'];

        if (
            $trackingCode === ''
            || preg_match('/[\x00-\x1F\x7F]/', $trackingCode)
        ) {
            throw ValidationException::withMessages([
                'tracking_code' =>
                    'Enter or scan a valid waybill tracking code.',
            ]);
        }

        $providerId = $this->providerId();

        return DB::transaction(function () use (
            $trackingCode,
            $method,
            $providerId
        ) {
            $shipment = DB::table('shipments')
                ->where('logistics_provider_id', $providerId)
                ->where('tracking_code', $trackingCode)
                ->lockForUpdate()
                ->first();

            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'No matching waybill was found for your Logistics account.',
                ], 404);
            }

            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $shipment->id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$sorting) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This parcel has not been received. Receive it in Incoming Parcels before verifying the waybill.',
                ], 422);
            }

            if (!in_array(
                $sorting->stage,
                ['received', 'sorted'],
                true
            )) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This parcel is not eligible for waybill verification.',
                ], 422);
            }

            $alreadyVerified =
                $sorting->waybill_verified_at !== null;

            if (
                !$alreadyVerified
                && $sorting->stage === 'sorted'
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This parcel was sorted before verification tracking was enabled. It requires administrative reconciliation.',
                ], 422);
            }

            if (!$alreadyVerified) {
                $now = now();

                DB::table('logistics_sorting')
                    ->where('id', $sorting->id)
                    ->update([
                        'waybill_verified_at' => $now,
                        'waybill_verified_by' => Auth::id(),
                        'waybill_verification_method' => $method,
                        'updated_at' => $now,
                    ]);

                DB::table('delivery_events')->insert([
                    'shipment_id' => $shipment->id,
                    'status' => 'waybill_verified',
                    'attempt' => max(
                        1,
                        (int) ($shipment->attempts ?? 0)
                    ),
                    'user_id' => Auth::id(),
                    'note' =>
                        'Waybill verified using '
                        . ($method === 'qr'
                            ? 'QR scanner.'
                            : 'manual tracking code entry.'),
                    'occurred_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                LogisticsNotification::create(
                    $providerId,
                    'waybill_verified',
                    'Parcel Waybill Verified',
                    'Waybill ' . $shipment->tracking_code
                        . ' was verified and is now eligible for sorting.',
                    '/logistics/parcel-sorting'
                );
            }

            $verifiedAt = $alreadyVerified
                ? $sorting->waybill_verified_at
                : now()->toDateTimeString();

            return response()->json([
                'success' => true,
                'already_verified' => $alreadyVerified,
                'message' => $alreadyVerified
                    ? 'Waybill was already verified.'
                    : 'Waybill verified successfully. Parcel is now eligible for sorting.',
                'shipment' => [
                    'id' => $shipment->id,
                    'tracking_code' => $shipment->tracking_code,
                    'status' => $shipment->status ?? 'Unknown',
                    'rider_id' => $shipment->rider_id,
                    'seller_order_id' => $shipment->seller_order_id,
                    'created_at' => $shipment->created_at,
                    'updated_at' => $shipment->updated_at,
                    'sorting_stage' => $sorting->stage,
                    'waybill_verified' => true,
                    'waybill_verified_at' => $verifiedAt,
                    'ready_for_sorting' =>
                        $sorting->stage === 'received',
                ],
            ]);
        }, 3);
    }
}
