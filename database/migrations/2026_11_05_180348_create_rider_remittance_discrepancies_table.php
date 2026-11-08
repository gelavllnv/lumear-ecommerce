<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_remittance_discrepancies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->foreignId('rider_remittance_id')
                ->nullable()
                ->constrained('rider_remittances')
                ->restrictOnDelete();

            $table->enum('type', [
                'shortage',
                'overage',
                'other'
            ]);

            $table->unsignedBigInteger('expected_amount_minor');
            $table->unsignedBigInteger('actual_amount_minor');
            $table->unsignedBigInteger('difference_minor');

            $table->enum('status', [
                'open',
                'under_review',
                'resolved',
                'dismissed'
            ])->default('open');

            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->foreignId('reported_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index([
                'logistics_provider_id',
                'status'
            ], 'rider_discrepancy_provider_status');

            $table->index([
                'rider_id',
                'status'
            ], 'rider_discrepancy_rider_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_remittance_discrepancies');
    }
};
