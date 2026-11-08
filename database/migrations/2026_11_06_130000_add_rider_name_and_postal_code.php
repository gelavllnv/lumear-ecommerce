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
