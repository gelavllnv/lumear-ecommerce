<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_central_fund_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3)->default('PHP');
            $table->timestamps();
        });

        Schema::create('logistics_central_fund_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('central_wallet_id')
                ->constrained('logistics_central_fund_wallets')
                ->restrictOnDelete();

            $table->string('reference', 80)->unique();
            $table->enum('direction', ['credit', 'debit']);
            $table->string('type', 50);
            $table->unsignedBigInteger('amount_minor');

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('remarks')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Short custom name: MySQL permits a maximum of 64 characters.
            $table->index(
                ['central_wallet_id', 'recorded_at'],
                'lcfe_wallet_recorded_idx'
            );
        });

        Schema::create('logistics_hub_payroll_wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_provider_id')
                ->unique()
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->string('currency', 3)->default('PHP');
            $table->timestamps();
        });

        Schema::create('logistics_hub_payroll_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hub_payroll_wallet_id')
                ->constrained('logistics_hub_payroll_wallets')
                ->restrictOnDelete();

            $table->string('reference', 80)->unique();
            $table->enum('direction', ['credit', 'debit']);
            $table->string('type', 50);
            $table->unsignedBigInteger('amount_minor');

            $table->foreignId('rider_payroll_id')
                ->nullable()
                ->constrained('rider_payrolls')
                ->nullOnDelete();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('remarks')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(
                ['hub_payroll_wallet_id', 'recorded_at'],
                'lhpe_wallet_recorded_idx'
            );
        });

        Schema::create('logistics_personal_salary_wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('currency', 3)->default('PHP');
            $table->timestamps();
        });

        Schema::create('logistics_personal_salary_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('personal_wallet_id')
                ->constrained('logistics_personal_salary_wallets')
                ->restrictOnDelete();

            $table->string('reference', 80)->unique();
            $table->enum('direction', ['credit', 'debit']);
            $table->string('type', 50);
            $table->unsignedBigInteger('amount_minor');

            $table->foreignId('rider_payroll_id')
                ->nullable()
                ->constrained('rider_payrolls')
                ->nullOnDelete();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('remarks')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(
                ['personal_wallet_id', 'recorded_at'],
                'lpse_wallet_recorded_idx'
            );
        });

        Schema::create('logistics_hub_admin_payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->foreignId('hub_admin_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('salary_matrix_id')
                ->nullable()
                ->constrained('logistics_admin_salary_matrix')
                ->nullOnDelete();

            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('salary_minor');

            $table->enum('status', [
                'draft',
                'paid',
                'void',
            ])->default('draft');

            $table->string('payment_reference', 80)->nullable();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(
                [
                    'logistics_provider_id',
                    'period_start',
                    'period_end',
                ],
                'hub_admin_payroll_period_unique'
            );
        });

        Schema::create('logistics_salary_distribution_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 80);
            $table->string('action', 80);

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('reference', 'lsda_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_salary_distribution_audit_logs');
        Schema::dropIfExists('logistics_hub_admin_payrolls');
        Schema::dropIfExists('logistics_personal_salary_entries');
        Schema::dropIfExists('logistics_personal_salary_wallets');
        Schema::dropIfExists('logistics_hub_payroll_entries');
        Schema::dropIfExists('logistics_hub_payroll_wallets');
        Schema::dropIfExists('logistics_central_fund_entries');
        Schema::dropIfExists('logistics_central_fund_wallets');
    }
};
