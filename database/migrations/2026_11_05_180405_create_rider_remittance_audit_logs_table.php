<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_remittance_audit_logs', function (Blueprint $table) {
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

            $table->foreignId('rider_cod_obligation_id')
                ->nullable()
                ->constrained('rider_cod_obligations')
                ->restrictOnDelete();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 100);

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index([
                'logistics_provider_id',
                'created_at'
            ], 'rider_remittance_audit_provider_date');

            $table->index([
                'rider_id',
                'created_at'
            ], 'rider_remittance_audit_rider_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_remittance_audit_logs');
    }
};
