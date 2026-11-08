<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'riders',
            'logistics_rider_applications',
        ] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('province', 255)->nullable();
                $table->string('municipality', 255)->nullable();
                $table->string('barangay', 255)->nullable();
                $table->string('municipality_psgc_code', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'riders',
            'logistics_rider_applications',
        ] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn([
                    'province',
                    'municipality',
                    'barangay',
                    'municipality_psgc_code',
                ]);
            });
        }
    }
};
