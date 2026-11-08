<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('riders', 'email')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('email')->nullable();
            });
        }

        if (! Schema::hasColumn('riders', 'phone')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('phone', 30)->nullable();
            });
        }

        DB::table('riders')->orderBy('id')->chunkById(100, function ($riders): void {
            foreach ($riders as $rider) {
                $user = DB::table('users')
                    ->where('id', $rider->user_id)
                    ->first(['email', 'phone', 'date_of_birth']);

                if (! $user) {
                    continue;
                }

                DB::table('riders')->where('id', $rider->id)->update([
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'date_of_birth' => $rider->date_of_birth ?: $user->date_of_birth,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('riders', 'phone')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->dropColumn('phone');
            });
        }

        if (Schema::hasColumn('riders', 'email')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->dropColumn('email');
            });
        }
    }
};
