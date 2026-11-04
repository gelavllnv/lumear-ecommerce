<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table): void {
            $table->string('job_type', 40)->nullable()->after('vehicle_type');
            $table->string('license_no', 100)->nullable()->after('plate_no');
            $table->date('license_expires_at')->nullable()->after('license_no');
            $table->string('sex', 20)->nullable()->after('license_expires_at');
            $table->date('date_of_birth')->nullable()->after('sex');
            $table->string('region')->nullable()->after('date_of_birth');
            $table->string('province')->nullable()->after('region');
            $table->string('municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('municipality');
            $table->string('street')->nullable()->after('barangay');
            $table->string('house_unit')->nullable()->after('street');
            $table->json('document_paths')->nullable()->after('house_unit');
        });

        Schema::table('logistics_rider_applications', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->unique()->after('id')
                ->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('sex', 20)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('sex');
            $table->string('region')->nullable()->after('date_of_birth');
            $table->string('province')->nullable()->after('region');
            $table->string('municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('municipality');
            $table->string('street')->nullable()->after('barangay');
            $table->string('house_unit')->nullable()->after('street');
            $table->string('job_type', 40)->nullable()->after('vehicle_type');
            $table->string('license_no', 100)->nullable()->after('plate_no');
            $table->json('document_paths')->nullable()->after('license_no');
        });

        Schema::create('rider_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_api_tokens');

        Schema::table('logistics_rider_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'phone', 'sex', 'date_of_birth', 'region', 'province',
                'municipality', 'barangay', 'street', 'house_unit', 'job_type',
                'license_no', 'document_paths',
            ]);
        });

        Schema::table('riders', function (Blueprint $table): void {
            $table->dropColumn([
                'job_type', 'license_no', 'license_expires_at', 'sex',
                'date_of_birth', 'region', 'province', 'municipality',
                'barangay', 'street', 'house_unit', 'document_paths',
            ]);
        });
    }
};
