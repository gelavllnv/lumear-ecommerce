<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_hub_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_task_id')
                ->unique()
                ->constrained('rider_tasks');

            $table->foreignId('shipment_id')
                ->constrained('shipments');

            $table->foreignId('rider_id')
                ->constrained('riders');

            $table->foreignId('source_provider_id')
                ->constrained('logistics_providers');

            $table->foreignId('destination_provider_id')
                ->constrained('logistics_providers');

            $table->string('status', 20)
                ->default('in_transit');

            $table->json('sorting_snapshot');

            $table->foreignId('dispatched_by')
                ->constrained('users');

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users');

            $table->timestamp('dispatched_at');
            $table->timestamp('received_at')->nullable();

            $table->string('condition', 120)->nullable();
            $table->text('receipt_remarks')->nullable();

            $table->timestamps();

            $table->index(['shipment_id', 'status']);
            $table->index(['destination_provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_hub_transfers');
    }
};