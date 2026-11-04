<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_sorting', function (Blueprint $table): void {
            if (!Schema::hasColumn('logistics_sorting', 'condition')) {
                $table->string('condition', 120)->nullable()->after('stage');
            }

            if (!Schema::hasColumn('logistics_sorting', 'remarks')) {
                $table->text('remarks')->nullable()->after('condition');
            }

            if (!Schema::hasColumn('logistics_sorting', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('received_at');
            }

            if (!Schema::hasColumn('logistics_sorting', 'verified_by')) {
                $table->foreignId('verified_by')
                    ->nullable()
                    ->after('verified_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('logistics_sorting', 'discrepancy_reviewed_at')) {
                $table->timestamp('discrepancy_reviewed_at')
                    ->nullable()
                    ->after('verified_by');
            }

            if (!Schema::hasColumn('logistics_sorting', 'discrepancy_reviewed_by')) {
                $table->foreignId('discrepancy_reviewed_by')
                    ->nullable()
                    ->after('discrepancy_reviewed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // The original table uses an ENUM. Add a dedicated discrepancy stage
        // so flagged parcels cannot accidentally move into sorting.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE logistics_sorting MODIFY stage " .
                "ENUM('received', 'sorted', 'discrepancy') " .
                "NOT NULL DEFAULT 'received'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "UPDATE logistics_sorting SET stage = 'received' " .
                "WHERE stage = 'discrepancy'"
            );
            DB::statement(
                "ALTER TABLE logistics_sorting MODIFY stage " .
                "ENUM('received', 'sorted') NOT NULL DEFAULT 'received'"
            );
        }

        Schema::table('logistics_sorting', function (Blueprint $table): void {
            $columns = [
                'discrepancy_reviewed_by',
                'discrepancy_reviewed_at',
                'verified_by',
                'verified_at',
                'remarks',
                'condition',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('logistics_sorting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
