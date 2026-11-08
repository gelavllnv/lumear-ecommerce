<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_return_cases', function (Blueprint $table) {
            $table->id();

            $table->string('return_reference', 64)->unique();

            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->restrictOnDelete();

            $table->foreignId('seller_order_id')
                ->constrained('seller_orders')
                ->restrictOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->string('buyer_name', 150);
            $table->string('buyer_phone', 30);

            $table->json('pickup_address');

            $table->text('return_reason');
            $table->text('seller_return_destination');

            $table->timestamp('approved_at');
            $table->timestamp('pickup_deadline')->nullable();

            $table->date('scheduled_date')->nullable();
            $table->time('schedule_start')->nullable();
            $table->time('schedule_end')->nullable();

            $table->foreignId('rider_id')
                ->nullable()
                ->constrained('riders')
                ->nullOnDelete();

            $table->foreignId('scheduled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('scheduled_at')->nullable();

            $table->timestamp('picked_up_at')->nullable();
            $table->string('pickup_condition', 120)->nullable();
            $table->text('pickup_remarks')->nullable();

            $table->foreignId('hub_received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('hub_received_at')->nullable();
            $table->string('hub_condition', 120)->nullable();
            $table->text('hub_remarks')->nullable();

            $table->foreignId('seller_handed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('seller_handed_at')->nullable();
            $table->string('seller_recipient_name', 150)->nullable();
            $table->text('seller_handoff_remarks')->nullable();

            $table->string('status', 40)->default('approved');

            $table->timestamps();

            $table->unique(
                'shipment_id',
                'return_case_one_per_shipment'
            );

            $table->index(
                ['logistics_provider_id', 'status'],
                'return_case_provider_status_idx'
            );

            $table->index(
                ['rider_id', 'scheduled_date'],
                'return_case_rider_schedule_idx'
            );
        });

        Schema::create('logistics_return_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('return_case_id')
                ->constrained('logistics_return_cases')
                ->cascadeOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->foreignId('rider_id')
                ->nullable()
                ->constrained('riders')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 40);
            $table->text('note')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(
                ['logistics_provider_id', 'occurred_at'],
                'return_event_provider_date_idx'
            );

            $table->index(
                ['return_case_id', 'occurred_at'],
                'return_event_case_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_return_events');
        Schema::dropIfExists('logistics_return_cases');
    }
};
