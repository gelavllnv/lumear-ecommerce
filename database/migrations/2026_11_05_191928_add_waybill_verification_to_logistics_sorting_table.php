<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_sorting', function (Blueprint $table) {
            $table->timestamp('waybill_verified_at')
                ->nullable();

            $table->foreignId('waybill_verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('waybill_verification_method', 20)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_sorting', function (Blueprint $table) {
            $table->dropForeign([
                'waybill_verified_by'
            ]);

            $table->dropColumn([
                'waybill_verified_at',
                'waybill_verified_by',
                'waybill_verification_method',
            ]);
        });
    }
};
