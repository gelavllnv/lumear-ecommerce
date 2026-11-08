<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. RIDER WORK DAYS
        |--------------------------------------------------------------------------
        |
        | Fixed daily base salary: PHP 630.00
        | Stored as 63000 centavos.
        |
        | One attendance record per rider per date.
        | Delivery counts do not determine base salary.
        |
        */

        Schema::create('rider_work_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->date('work_date');

            $table->enum('attendance_status', [
                'present',
                'absent',
                'approved_leave',
                'pending_review',
            ])->default('pending_review');

            $table->boolean('is_paid_day')
                ->default(false);

            $table->unsignedBigInteger('base_salary_minor')
                ->default(63000);

            $table->text('remarks')->nullable();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['rider_id', 'work_date'],
                'rider_work_day_unique'
            );

            $table->index([
                'logistics_provider_id',
                'work_date',
            ], 'rider_work_provider_date_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 2. RIDER DELIVERY COMMISSIONS
        |--------------------------------------------------------------------------
        |
        | PHP 5.00 for each successfully delivered parcel.
        |
        | One commission record per shipment.
        | Failed deliveries and RTS do not earn commission.
        |
        */

        Schema::create(
            'rider_delivery_commissions',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('rider_id')
                    ->constrained('riders')
                    ->restrictOnDelete();

                $table->foreignId('logistics_provider_id')
                    ->constrained('logistics_providers')
                    ->restrictOnDelete();

                $table->foreignId('shipment_id')
                    ->constrained('shipments')
                    ->restrictOnDelete();

                $table->unsignedBigInteger('amount_minor')
                    ->default(500);

                $table->string('delivery_status', 40)
                    ->default('delivered');

                $table->timestamp('earned_at');

                $table->timestamps();

                $table->unique('shipment_id');

                $table->index([
                    'rider_id',
                    'earned_at',
                ], 'rider_commission_date_idx');

                $table->index([
                    'logistics_provider_id',
                    'earned_at',
                ], 'rider_commission_provider_idx');
            }
        );

        /*
        |--------------------------------------------------------------------------
        | 3. RIDER PAYROLLS
        |--------------------------------------------------------------------------
        |
        | Stores payroll totals as snapshots.
        |
        | Draft -> Approved -> Paid
        |
        | All monetary values are in centavos.
        |
        */

        Schema::create('rider_payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')
                ->constrained('riders')
                ->restrictOnDelete();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->date('period_start');
            $table->date('period_end');

            $table->unsignedInteger('paid_days')
                ->default(0);

            $table->unsignedInteger('successful_deliveries')
                ->default(0);

            $table->unsignedBigInteger('base_salary_minor')
                ->default(0);

            $table->unsignedBigInteger('commission_minor')
                ->default(0);

            $table->unsignedBigInteger('gross_pay_minor')
                ->default(0);

            $table->enum('status', [
                'draft',
                'approved',
                'paid',
                'void',
            ])->default('draft');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();

            $table->string('payment_reference')
                ->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'rider_id',
                    'period_start',
                    'period_end',
                ],
                'rider_payroll_period_unique'
            );

            $table->index([
                'logistics_provider_id',
                'status',
            ], 'rider_payroll_provider_status_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | 4. RIDER PAYROLL AUDIT LOGS
        |--------------------------------------------------------------------------
        |
        | Records important payroll actions.
        |
        */

        Schema::create(
            'rider_payroll_audit_logs',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('rider_payroll_id')
                    ->nullable()
                    ->constrained('rider_payrolls')
                    ->restrictOnDelete();

                $table->foreignId('rider_id')
                    ->constrained('riders')
                    ->restrictOnDelete();

                $table->foreignId('logistics_provider_id')
                    ->constrained('logistics_providers')
                    ->restrictOnDelete();

                $table->foreignId('performed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('action', 80);

                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();

                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->index([
                    'logistics_provider_id',
                    'created_at',
                ], 'rider_payroll_audit_provider_idx');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_payroll_audit_logs');
        Schema::dropIfExists('rider_payrolls');
        Schema::dropIfExists('rider_delivery_commissions');
        Schema::dropIfExists('rider_work_days');
    }
};
