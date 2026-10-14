<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Additive only: existing seller accounts and documents remain untouched. */
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            $table->string('business_type', 30)->nullable()->after('business_name');
            $table->string('business_registration_path')->nullable()->after('tax_number');
            $table->string('bir_certificate_path')->nullable()->after('business_registration_path');
        });

        Schema::table('seller_kyc_submissions', function (Blueprint $table): void {
            $table->string('business_type', 30)->nullable()->after('seller_id');
            $table->string('business_registration_path')->nullable()->after('business_permit_path');
            $table->string('bir_certificate_path')->nullable()->after('business_registration_path');
        });

        Schema::create('email_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
        Schema::table('seller_kyc_submissions', function (Blueprint $table): void {
            $table->dropColumn(['business_type', 'business_registration_path', 'bir_certificate_path']);
        });
        Schema::table('seller_profiles', function (Blueprint $table): void {
            $table->dropColumn(['business_type', 'business_registration_path', 'bir_certificate_path']);
        });
    }
};
