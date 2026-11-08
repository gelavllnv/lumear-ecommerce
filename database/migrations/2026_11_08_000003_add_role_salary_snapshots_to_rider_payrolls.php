<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_payrolls', function (Blueprint $table) {
            $table->string('payroll_rule', 30)
                ->default('daily')
                ->after('successful_deliveries');

            $table->unsignedBigInteger('monthly_salary_minor')
                ->default(0)
                ->after('payroll_rule');

            $table->unsignedInteger('calendar_days')
                ->default(0)
                ->after('monthly_salary_minor');
        });
    }

    public function down(): void
    {
        Schema::table('rider_payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'payroll_rule',
                'monthly_salary_minor',
                'calendar_days',
            ]);
        });
    }
};
