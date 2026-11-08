<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_account_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('action', 80);
            $table->string('description', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['logistics_provider_id', 'created_at'],
                'la_provider_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_account_activities');
    }
};
