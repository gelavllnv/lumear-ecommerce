<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnDelete();

            $table->foreignId('rider_id')
                ->constrained('riders');

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers');

            $table->foreignId('destination_provider_id')
                ->nullable()
                ->constrained('logistics_providers');

            $table->string('job_type', 40);

            $table->string('status', 30)
                ->default('assigned');

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['shipment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_tasks');
    }
};