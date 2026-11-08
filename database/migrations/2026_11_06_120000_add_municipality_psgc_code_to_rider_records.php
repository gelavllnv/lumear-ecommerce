<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['logistics_rider_applications', 'riders'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'municipality_psgc_code')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('municipality_psgc_code', 20)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['riders', 'logistics_rider_applications'] as $tableName) {
            if (Schema::hasColumn($tableName, 'municipality_psgc_code')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('municipality_psgc_code');
                });
            }
        }
    }
};
