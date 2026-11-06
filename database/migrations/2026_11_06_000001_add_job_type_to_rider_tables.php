<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->string('job_type', 40)->nullable()->index();
        });

        Schema::table('logistics_rider_applications', function (Blueprint $table) {
            $table->string('job_type', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_rider_applications', function (Blueprint $table) {
            $table->dropIndex(['job_type']);
            $table->dropColumn('job_type');
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->dropIndex(['job_type']);
            $table->dropColumn('job_type');
        });
    }
};