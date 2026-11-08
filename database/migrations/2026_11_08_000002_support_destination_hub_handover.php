<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_hub_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('dispatched_by')->nullable()->change();
            $table->timestamp('dispatched_at')->nullable()->change();

            $table->boolean('origin_dispatch_confirmed')->default(true);

            $table->foreignId('registered_by')
                ->nullable()
                ->constrained('users');

            $table->timestamp('registered_at')->nullable();
        });
    }

    public function down(): void
    {
        if (
            DB::table('logistics_hub_transfers')
                ->whereNull('dispatched_by')
                ->orWhereNull('dispatched_at')
                ->exists()
        ) {
            throw new RuntimeException(
                'Review destination-registered handovers before reverting this migration. Departure details must not be invented.'
            );
        }

        Schema::table('logistics_hub_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by');

            $table->dropColumn([
                'registered_at',
                'origin_dispatch_confirmed',
            ]);

            $table->unsignedBigInteger('dispatched_by')
                ->nullable(false)
                ->change();

            $table->timestamp('dispatched_at')
                ->nullable(false)
                ->change();
        });
    }
};
