<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsWalletService
{
    /*
    |--------------------------------------------------------------------------
    | LUMEAR SHIPPING AND COMMISSION RULES
    |--------------------------------------------------------------------------
    |
    | General shipping fee: ₱50.00
    | Logistics commission: 10%
    | Commission per successful delivery: ₱5.00
    |
    | All monetary values are stored in centavos.
    |
    */

    private const SHIPPING_FEE_MINOR = 5000;

    private const COMMISSION_BASIS_POINTS = 1000;

    /*
    |--------------------------------------------------------------------------
    | CREDIT COMMISSION AFTER SUCCESSFUL DELIVERY
    |--------------------------------------------------------------------------
    |
    | This method is called by LogisticsDeliveryApiController
    | when a shipment is successfully marked as delivered.
    |
    | Returns:
    | true  = New commission credited
    | false = Commission already exists
    |
    */

    public function creditDeliveredShipment(int $shipmentId): bool
    {
        return DB::transaction(function () use ($shipmentId) {

            /*
            |--------------------------------------------------------------------------
            | STEP 1: FIND AND LOCK THE SHIPMENT
            |--------------------------------------------------------------------------
            */

            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->lockForUpdate()
                ->first();

            if (!$shipment) {
                throw ValidationException::withMessages([
                    'shipment' => 'Shipment not found.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 2: VERIFY SUCCESSFUL DELIVERY
            |--------------------------------------------------------------------------
            */

            if ($shipment->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'shipment' =>
                        'Logistics commission can only be earned after successful delivery.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 3: PREVENT DUPLICATE COMMISSIONS
            |--------------------------------------------------------------------------
            */

            $alreadyCredited = DB::table(
                'logistics_wallet_transactions'
            )
                ->where('shipment_id', $shipmentId)
                ->exists();

            if ($alreadyCredited) {
                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 4: VERIFY LOGISTICS PROVIDER
            |--------------------------------------------------------------------------
            */

            $provider = DB::table('logistics_providers')
                ->where('id', $shipment->logistics_provider_id)
                ->first();

            if (!$provider) {
                throw ValidationException::withMessages([
                    'provider' =>
                        'The Logistics provider assigned to this shipment was not found.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 5: VERIFY SELLER ORDER OWNERSHIP
            |--------------------------------------------------------------------------
            |
            | The shipment and seller order must belong to the
            | same Logistics provider.
            |
            */

            $sellerOrder = DB::table('seller_orders')
                ->where('id', $shipment->seller_order_id)
                ->where('logistics_provider_id', $provider->id)
                ->first();

            if (!$sellerOrder) {
                throw ValidationException::withMessages([
                    'order' =>
                        'The shipment does not belong to a valid Logistics order.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 6: CALCULATE FIXED COMMISSION
            |--------------------------------------------------------------------------
            |
            | ₱50 shipping fee = 5000 centavos
            | 10% commission = 500 centavos
            | ₱5.00 per successful delivery
            |
            */

            $shippingFeeMinor = self::SHIPPING_FEE_MINOR;

            $commissionMinor = intdiv(
                $shippingFeeMinor * self::COMMISSION_BASIS_POINTS,
                10000
            );

            /*
            |--------------------------------------------------------------------------
            | STEP 7: CREATE WALLET IF IT DOES NOT EXIST
            |--------------------------------------------------------------------------
            */

            DB::table('logistics_wallets')->insertOrIgnore([
                'logistics_provider_id' => $provider->id,
                'currency' => 'PHP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | STEP 8: RETRIEVE LOGISTICS WALLET
            |--------------------------------------------------------------------------
            */

            $wallet = DB::table('logistics_wallets')
                ->where('logistics_provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw ValidationException::withMessages([
                    'wallet' =>
                        'Unable to create or retrieve the Logistics E-Wallet.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 9: RECORD THE COMMISSION
            |--------------------------------------------------------------------------
            |
            | The shipment_id column has a UNIQUE constraint.
            | This prevents the same shipment from being
            | credited more than once.
            |
            */

            DB::table('logistics_wallet_transactions')->insert([
                'logistics_wallet_id' => $wallet->id,
                'shipment_id' => $shipment->id,
                'type' => 'commission',
                'shipping_fee_minor' => $shippingFeeMinor,
                'commission_rate_basis_points' =>
                    self::COMMISSION_BASIS_POINTS,
                'amount_minor' => $commissionMinor,
                'earned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }
}
