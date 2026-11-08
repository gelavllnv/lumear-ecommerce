<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_wallet_transactions', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | Salary payments do not belong to a shipment.
            |
            | Remove the existing shipment foreign key and unique index first,
            | make shipment_id optional, then restore it as a normal foreign key.
            |--------------------------------------------------------------------------
            */

            $table->dropForeign(
                'logistics_wallet_transactions_shipment_id_foreign'
            );

            $table->dropUnique(
                'logistics_wallet_transactions_shipment_id_unique'
            );

            $table->foreignId('shipment_id')
                ->nullable()
                ->change();

            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Salary payment reference from the Head / Super Admin.
            |--------------------------------------------------------------------------
            */

            $table->string('reference', 80)
                ->nullable()
                ->unique()
                ->after('shipment_id');

            /*
            |--------------------------------------------------------------------------
            | Salary has no shipping fee, so this must default to zero.
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('shipping_fee_minor')
                ->default(0)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(
                'logistics_wallet_transactions_shipment_id_foreign'
            );

            $table->dropUnique(
                'logistics_wallet_transactions_reference_unique'
            );

            $table->dropColumn('reference');

            $table->foreignId('shipment_id')
                ->nullable(false)
                ->change();

            $table->unique(
                'shipment_id',
                'logistics_wallet_transactions_shipment_id_unique'
            );

            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->restrictOnDelete();
        });
    }
};
