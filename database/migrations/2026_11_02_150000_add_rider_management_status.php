<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('riders') &&
            !Schema::hasColumn('riders', 'management_status')
        ) {
            Schema::table('riders', function (Blueprint $table) {
                $table->string('management_status', 20)
                    ->default('Offline');
                $table->text('management_remarks')->nullable();
            });
        }

        if (
            Schema::hasTable('logistics_rider_applications') &&
            !Schema::hasColumn('logistics_rider_applications', 'remarks')
        ) {
            Schema::table('logistics_rider_applications', function (Blueprint $table) {
                $table->text('remarks')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('riders', 'management_status')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->dropColumn([
                    'management_status',
                    'management_remarks',
                ]);
            });
        }

        if (Schema::hasColumn('logistics_rider_applications', 'remarks')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table) {
                $table->dropColumn('remarks');
            });
        }
    }
};
