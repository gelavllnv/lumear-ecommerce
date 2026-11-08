<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('rider_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained();
            $table->foreignId('logistics_provider_id')->constrained();
            $table->foreignId('destination_provider_id')->nullable()->constrained('logistics_providers');
            $table->string('job_type', 40);
            $table->string('status', 30)->default('assigned');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'status']);
            $table->index(['shipment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_tasks');
        Schema::dropIfExists('mobile_api_tokens');
    }
};
