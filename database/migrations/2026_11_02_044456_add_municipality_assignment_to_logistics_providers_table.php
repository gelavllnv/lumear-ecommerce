
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->string('municipality_psgc_code', 20)
                ->nullable()
                ->after('contact_phone');

            $table->string('municipality_name', 150)
                ->nullable()
                ->after('municipality_psgc_code');

            $table->string('province_name', 150)
                ->nullable()
                ->after('municipality_name');
        });

        // Only approved providers reserve a municipality.
        // MySQL allows multiple NULL values in a UNIQUE index.
        DB::statement("
            ALTER TABLE logistics_providers
            ADD COLUMN approved_municipality_code VARCHAR(20)
            GENERATED ALWAYS AS (
                CASE
                    WHEN status = 'approved'
                    THEN municipality_psgc_code
                    ELSE NULL
                END
            ) STORED
        ");

        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->unique(
                'approved_municipality_code',
                'logistics_approved_municipality_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropUnique(
                'logistics_approved_municipality_unique'
            );
        });

        DB::statement("
            ALTER TABLE logistics_providers
            DROP COLUMN approved_municipality_code
        ");

        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropColumn([
                'municipality_psgc_code',
                'municipality_name',
                'province_name',
            ]);
        });
    }
};
