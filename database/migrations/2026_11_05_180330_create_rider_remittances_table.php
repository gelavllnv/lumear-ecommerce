<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_remittances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->string('remittance_number', 40)->unique();

            $table->enum('payment_method', [
                'gcash',
                'maya',
                'bank_transfer',
                'other'
            ]);

            $table->string('payment_provider', 100)->nullable();

            $table->string('transaction_reference', 150);

            $table->unsignedBigInteger('submitted_amount_minor');

            $table->string('receipt_path', 500)->nullable();

            $table->enum('status', [
                'pending_verification',
                'verified',
                'rejected',
                'cancelled'
            ])->default('pending_verification');

            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('submitted_at');

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->text('verification_remarks')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->unique([
                'logistics_provider_id',
                'payment_method',
                'transaction_reference'
            ], 'rider_remittance_unique_reference');

            $table->index([
                'logistics_provider_id',
                'status'
            ], 'rider_remittance_provider_status');

            $table->index([
                'rider_id',
                'submitted_at'
            ], 'rider_remittance_rider_submitted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_remittances');
    }
};
