<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_generated_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->cascadeOnDelete();

            $table->foreignId('generated_by')
                ->constrained('users');

            $table->string('report_type', 30);
            $table->string('status_filter', 30);

            $table->date('date_from');
            $table->date('date_to');

            $table->unsignedInteger('record_count');

            $table->string('file_path');
            $table->char('sha256', 64);

            $table->timestamps();

            // FIX: Short index name to avoid MySQL's
            // 64-character identifier limit.
            $table->index(
                ['logistics_provider_id', 'created_at'],
                'lgr_provider_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_generated_reports');
    }
};
