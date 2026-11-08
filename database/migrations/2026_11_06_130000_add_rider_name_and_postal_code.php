<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('riders', 'name')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('name')->nullable();
            });
        }

        if (! Schema::hasColumn('riders', 'postal_code')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('postal_code', 10)->nullable();
            });
        }

        // The existing rider records are seeded from their application. Older
        // installations did not yet have this application column, so create it
        // before reading it during the backfill below.
        if (! Schema::hasColumn('logistics_rider_applications', 'postal_code')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('postal_code', 10)->nullable();
            });
        }

        DB::table('riders')->orderBy('id')->chunkById(100, function ($riders): void {
            foreach ($riders as $rider) {
                $name = DB::table('users')->where('id', $rider->user_id)->value('name');
                $postalCode = DB::table('logistics_rider_applications')
                    ->where('user_id', $rider->user_id)
                    ->whereNotNull('postal_code')
                    ->orderByDesc('id')
                    ->value('postal_code');

                DB::table('riders')->where('id', $rider->id)->update([
                    'name' => $name,
                    'postal_code' => $postalCode,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('riders', 'postal_code')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->dropColumn('postal_code');
            });
        }

        if (Schema::hasColumn('riders', 'name')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
    }
};
