<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->cascadeOnDelete();

            $table->string('type', 60);
            $table->string('title', 150);
            $table->text('message');
            $table->string('url', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(
                ['logistics_provider_id', 'created_at'],
                'ln_provider_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_notifications');
    }
};
