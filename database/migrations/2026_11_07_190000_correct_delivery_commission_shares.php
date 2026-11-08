<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_delivery_commissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_minor')
                ->default(4000)
                ->change();
        });

        Schema::table('logistics_wallet_transactions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('commission_rate_basis_points')
                ->default(2000)
                ->change();
        });

        DB::table('rider_delivery_commissions')
            ->where('delivery_status', 'delivered')
            ->where('amount_minor', 500)
            ->update([
                'amount_minor' => 4000,
                'updated_at' => now(),
            ]);

        DB::table('logistics_wallet_transactions')
            ->where('type', 'commission')
            ->where('amount_minor', 500)
            ->update([
                'amount_minor' => 1000,
                'commission_rate_basis_points' => 2000,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Corrected historical commission ledger entries are not reversed.
        Schema::table('rider_delivery_commissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_minor')
                ->default(500)
                ->change();
        });

        Schema::table('logistics_wallet_transactions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('commission_rate_basis_points')
                ->default(1000)
                ->change();
        });
    }
};
