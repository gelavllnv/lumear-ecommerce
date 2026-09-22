<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('seller_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained();

            $table->foreignId('product_variant_id')
                ->constrained();

            $table->string('product_name');
            $table->string('variant_name');

            $table->unsignedInteger('unit_price_minor');
            $table->unsignedInteger('quantity');

            $table->timestamps();

            $table->index('seller_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};