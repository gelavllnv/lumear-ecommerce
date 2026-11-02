<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('logistics_sorting_batches')) {
            Schema::create('logistics_sorting_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('logistics_provider_id')
                    ->constrained('logistics_providers')
                    ->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('destination', 255);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (
            Schema::hasTable('logistics_sorting') &&
            !Schema::hasColumn('logistics_sorting', 'batch_id')
        ) {
            Schema::table('logistics_sorting', function (Blueprint $table) {
                $table->foreignId('batch_id')
                    ->nullable()
                    ->constrained('logistics_sorting_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('logistics_sorting', 'batch_id')) {
            Schema::table('logistics_sorting', function (Blueprint $table) {
                $table->dropConstrainedForeignId('batch_id');
            });
        }

        Schema::dropIfExists('logistics_sorting_batches');
    }
};
