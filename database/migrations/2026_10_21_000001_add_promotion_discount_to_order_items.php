<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keep existing orders unchanged while recording line-level voucher discounts for new COD orders. */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('promotion_discount_minor')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('promotion_discount_minor');
        });
    }
};
