<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('logistics_sorting')) {
            Schema::create('logistics_sorting', function (Blueprint $table) {
                $table->id();

                $table->foreignId('shipment_id')
                    ->unique()
                    ->constrained('shipments');

                $table->foreignId('logistics_provider_id')
                    ->constrained('logistics_providers');

                $table->enum('stage', [
                    'received',
                    'sorted',
                ])->default('received');

                $table->timestamp('received_at')->nullable();
                $table->timestamp('sorted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('logistics_rider_applications')) {
            Schema::create('logistics_rider_applications', function (Blueprint $table) {
                $table->id();

                $table->foreignId('logistics_provider_id')
                    ->constrained('logistics_providers');

                $table->string('name');
                $table->string('email');
                $table->string('vehicle_type');
                $table->string('plate_no');

                $table->enum('status', [
                    'pending',
                    'approved',
                    'rejected',
                ])->default('pending');

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('logistics_messages')) {
            Schema::create('logistics_messages', function (Blueprint $table) {
                $table->id();

                $table->foreignId('logistics_provider_id')
                    ->constrained('logistics_providers');

                $table->foreignId('sender_id')
                    ->constrained('users');

                $table->foreignId('recipient_id')
                    ->constrained('users');

                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_messages');
        Schema::dropIfExists('logistics_rider_applications');
        Schema::dropIfExists('logistics_sorting');
    }
};
