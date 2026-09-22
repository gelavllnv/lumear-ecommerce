<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('shipment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('status');
            $table->unsignedInteger('attempt')->default(1);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->text('note')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->unique([
                'shipment_id',
                'status',
                'attempt'
            ]);

            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
    }
};