<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_cod_obligations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->foreignId('shipment_id')
                ->unique()
                ->constrained('shipments')
                ->restrictOnDelete();

            $table->unsignedBigInteger('expected_amount_minor');
            $table->unsignedBigInteger('settled_amount_minor')->default(0);

            $table->enum('status', [
                'pending',
                'partially_settled',
                'settled',
                'disputed',
                'cancelled'
            ])->default('pending');

            $table->timestamp('collected_at');
            $table->timestamp('due_at');
            $table->timestamp('settled_at')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index([
                'logistics_provider_id',
                'status',
                'due_at'
            ], 'cod_obligations_provider_status_due');

            $table->index([
                'rider_id',
                'status'
            ], 'cod_obligations_rider_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_cod_obligations');
    }
};
