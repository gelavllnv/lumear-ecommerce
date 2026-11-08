<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->string('pickup_status', 30)->default('not_started');
        });

        Schema::table('rider_tasks', function (Blueprint $table): void {
            $table->foreignId('return_request_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rider_tasks', function (Blueprint $table): void {
            $table->dropForeign(['return_request_id']);
            $table->dropUnique(['return_request_id']);
            $table->dropColumn('return_request_id');
        });

        Schema::table('return_requests', function (Blueprint $table): void {
            $table->dropColumn('pickup_status');
        });
    }
};
