<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('riders', 'job_type')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('job_type', 40)->nullable()->after('vehicle_type');
            });
        }
        if (!Schema::hasColumn('riders', 'license_no')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('license_no', 100)->nullable()->after('plate_no');
            });
        }
        if (!Schema::hasColumn('riders', 'license_expires_at')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->date('license_expires_at')->nullable()->after('license_no');
            });
        }
        if (!Schema::hasColumn('riders', 'sex')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('sex', 20)->nullable()->after('license_expires_at');
            });
        }
        if (!Schema::hasColumn('riders', 'date_of_birth')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->date('date_of_birth')->nullable()->after('sex');
            });
        }
        if (!Schema::hasColumn('riders', 'region')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('region')->nullable()->after('date_of_birth');
            });
        }
        if (!Schema::hasColumn('riders', 'province')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('province')->nullable()->after('region');
            });
        }
        if (!Schema::hasColumn('riders', 'municipality')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('municipality')->nullable()->after('province');
            });
        }
        if (!Schema::hasColumn('riders', 'barangay')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('barangay')->nullable()->after('municipality');
            });
        }
        if (!Schema::hasColumn('riders', 'street')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('street')->nullable()->after('barangay');
            });
        }
        if (!Schema::hasColumn('riders', 'house_unit')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->string('house_unit')->nullable()->after('street');
            });
        }
        if (!Schema::hasColumn('riders', 'document_paths')) {
            Schema::table('riders', function (Blueprint $table): void {
                $table->json('document_paths')->nullable()->after('house_unit');
            });
        }

        if (!Schema::hasColumn('logistics_rider_applications', 'user_id')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->unique()->after('id')
                    ->constrained()->nullOnDelete();
            });
        } elseif (!Schema::hasIndex('logistics_rider_applications', ['user_id'], 'unique')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->unique('user_id');
            });
        }

        if (!Schema::hasColumn('logistics_rider_applications', 'phone')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('phone', 30)->nullable()->after('email');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'sex')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('sex', 20)->nullable()->after('phone');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'date_of_birth')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->date('date_of_birth')->nullable()->after('sex');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'region')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('region')->nullable()->after('date_of_birth');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'province')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('province')->nullable()->after('region');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'municipality')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('municipality')->nullable()->after('province');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'barangay')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('barangay')->nullable()->after('municipality');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'street')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('street')->nullable()->after('barangay');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'house_unit')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('house_unit')->nullable()->after('street');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'job_type')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('job_type', 40)->nullable()->after('vehicle_type');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'license_no')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->string('license_no', 100)->nullable()->after('plate_no');
            });
        }
        if (!Schema::hasColumn('logistics_rider_applications', 'document_paths')) {
            Schema::table('logistics_rider_applications', function (Blueprint $table): void {
                $table->json('document_paths')->nullable()->after('license_no');
            });
        }

        if (!Schema::hasTable('rider_api_tokens')) {
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
    }

    public function down(): void
    {
        /** Existing installations may have profile columns before this migration ran. */
        Schema::dropIfExists('rider_api_tokens');
    }
};
