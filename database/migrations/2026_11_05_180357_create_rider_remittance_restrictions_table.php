<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_remittance_restrictions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->enum('restriction_type', [
                'overdue_remittance',
                'discrepancy_review',
                'manual_review'
            ]);

            $table->enum('status', [
                'active',
                'lifted'
            ])->default('active');

            $table->boolean('blocks_new_assignments')->default(true);
            $table->boolean('blocks_pickup_scanning')->default(true);

            $table->text('reason')->nullable();

            $table->timestamp('restricted_at');
            $table->timestamp('lifted_at')->nullable();

            $table->foreignId('restricted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('lifted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('lift_reason')->nullable();

            $table->timestamps();

            $table->index([
                'rider_id',
                'status'
            ], 'rider_restriction_rider_status');

            $table->index([
                'logistics_provider_id',
                'status'
            ], 'rider_restriction_provider_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_remittance_restrictions');
    }
};
