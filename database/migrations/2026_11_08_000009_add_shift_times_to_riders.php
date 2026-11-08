<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->time('shift_start_time')->nullable()->after('job_type');
            $table->time('shift_end_time')->nullable()->after('shift_start_time');
        });

        DB::table('riders')->whereIn('job_type', ['Delivery', 'Seller Pickup'])
            ->update(['shift_start_time' => '07:00:00', 'shift_end_time' => '17:00:00']);

        DB::table('riders')->where('job_type', 'Long-distance Handover')
            ->update(['shift_start_time' => '20:00:00', 'shift_end_time' => '05:00:00']);
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['shift_start_time', 'shift_end_time']);
        });
    }
};
