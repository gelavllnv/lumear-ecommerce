<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_bug_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->string('affected_screen', 120);
            $table->string('app_version', 40)->nullable();
            $table->string('device_info')->nullable();
            $table->text('description');
            $table->text('expected_result')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->string('status', 30)->default('Pending');
            $table->text('admin_response')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_bug_reports');
    }
};
