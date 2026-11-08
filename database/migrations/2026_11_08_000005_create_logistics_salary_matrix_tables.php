<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'logistics_salary_location_tiers',
            function (Blueprint $table) {
                $table->id();

                $table->string('code', 30)->unique();

                $table->string('name', 150);

                $table->text('description')->nullable();

                $table->unsignedBigInteger(
                    'minimum_monthly_salary_minor'
                );

                /*
                | Null means there is no fixed maximum.
                | Tier 1 is ₱22,000 to ₱35,000+.
                */
                $table->unsignedBigInteger(
                    'maximum_monthly_salary_minor'
                )->nullable();

                $table->timestamps();
            }
        );

        Schema::table(
            'logistics_providers',
            function (Blueprint $table) {
                $table->foreignId('salary_location_tier_id')
                    ->nullable()
                    ->after('province_name')
                    ->constrained(
                        'logistics_salary_location_tiers'
                    )
                    ->nullOnDelete();
            }
        );

        Schema::create(
            'logistics_admin_salary_matrix',
            function (Blueprint $table) {
                $table->id();

                /*
                | logistics_admin = city/bayan hub Admin
                | head_super_admin = central Logistics Head Admin
                */
                $table->string('role', 40);

                /*
                | Hub Admin uses a tier.
                | Head/Super Admin does not use a city tier.
                */
                $table->foreignId('location_tier_id')
                    ->nullable()
                    ->constrained(
                        'logistics_salary_location_tiers'
                    )
                    ->nullOnDelete();

                $table->unsignedBigInteger(
                    'minimum_monthly_salary_minor'
                );

                $table->unsignedBigInteger(
                    'maximum_monthly_salary_minor'
                )->nullable();

                /*
                | Exact monthly salary used by future payroll.
                */
                $table->unsignedBigInteger(
                    'standard_monthly_salary_minor'
                );

                $table->date('effective_from');

                $table->date('effective_to')->nullable();

                $table->boolean('is_active')->default(true);

                $table->foreignId('set_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'role',
                    'location_tier_id',
                    'is_active',
                ], 'logistics_admin_salary_matrix_active_idx');
            }
        );

        DB::table('logistics_salary_location_tiers')
            ->insert([
                [
                    'code' => 'tier_1',
                    'name' => 'Tier 1: NCR / Central Business Districts',
                    'description' => 'Examples: Makati, Taguig, Pasig, Quezon City.',
                    'minimum_monthly_salary_minor' => 2200000,
                    'maximum_monthly_salary_minor' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'tier_2',
                    'name' => 'Tier 2: High-Growth Industrial Hubs',
                    'description' => 'Examples: Calamba, Biñan, Santa Rosa, Cavite City.',
                    'minimum_monthly_salary_minor' => 1800000,
                    'maximum_monthly_salary_minor' => 2600000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'tier_3',
                    'name' => 'Tier 3: Provincial Municipalities',
                    'description' => 'Examples: Santa Cruz, Famy, Balanga City, rural towns.',
                    'minimum_monthly_salary_minor' => 1500000,
                    'maximum_monthly_salary_minor' => 2000000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'logistics_admin_salary_matrix'
        );

        Schema::table(
            'logistics_providers',
            function (Blueprint $table) {
                $table->dropConstrainedForeignId(
                    'salary_location_tier_id'
                );
            }
        );

        Schema::dropIfExists(
            'logistics_salary_location_tiers'
        );
    }
};
