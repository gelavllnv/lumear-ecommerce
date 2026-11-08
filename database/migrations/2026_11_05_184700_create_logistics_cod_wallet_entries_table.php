<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_cod_wallet_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->foreignId('rider_remittance_id')
                ->unique()
                ->constrained('rider_remittances')
                ->restrictOnDelete();

            $table->unsignedBigInteger('received_amount_minor');
            $table->unsignedBigInteger('allocated_amount_minor');
            $table->unsignedBigInteger('unallocated_amount_minor');

            $table->string('source', 40)
                ->default('lumear_remittance');

            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index([
                'logistics_provider_id',
                'recorded_at'
            ], 'cod_wallet_provider_recorded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_cod_wallet_entries');
    }
};
