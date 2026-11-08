<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create Logistics E-Wallet tables.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | LOGISTICS WALLETS
        |--------------------------------------------------------------------------
        | Each Logistics provider has exactly one wallet.
        | Balances will be calculated from recorded transactions.
        */

        Schema::create('logistics_wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_provider_id')
                ->unique()
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->string('currency', 3)->default('PHP');

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | LOGISTICS WALLET TRANSACTIONS
        |--------------------------------------------------------------------------
        | All amounts are stored in centavos.
        |
        | Example:
        | ₱50 shipping fee = 5000 centavos
        | 10% commission = 500 centavos
        |
        | A shipment can only have one commission credit.
        */

        Schema::create('logistics_wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_wallet_id')
                ->constrained('logistics_wallets')
                ->restrictOnDelete();

            $table->foreignId('shipment_id')
                ->unique()
                ->constrained('shipments')
                ->restrictOnDelete();

            $table->string('type', 30)->default('commission');

            $table->unsignedBigInteger('shipping_fee_minor');

            $table->unsignedSmallInteger('commission_rate_basis_points')
                ->default(1000);

            $table->unsignedBigInteger('amount_minor');

            $table->timestamp('earned_at');

            $table->timestamps();

            $table->index([
                'logistics_wallet_id',
                'earned_at'
            ], 'logistics_wallet_earned_index');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_wallet_transactions');
        Schema::dropIfExists('logistics_wallets');
    }
};
