<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_remittance_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_remittance_id')
                ->constrained('rider_remittances')
                ->restrictOnDelete();

            $table->foreignId('rider_cod_obligation_id')
                ->constrained('rider_cod_obligations')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');

            $table->timestamps();

            $table->unique([
                'rider_remittance_id',
                'rider_cod_obligation_id'
            ], 'rider_remittance_allocation_unique');

            $table->index(
                'rider_cod_obligation_id',
                'rider_allocation_obligation_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_remittance_allocations');
    }
};
