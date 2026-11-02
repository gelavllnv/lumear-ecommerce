
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_applications', function (Blueprint $table) {
            $table->id();

            // Connect this application to the existing provider.
            $table->foreignId('logistics_provider_id')
                ->unique()
                ->constrained('logistics_providers')
                ->cascadeOnDelete();

            // Business registration details
            $table->string('company_name', 150);
            $table->string('sec_number', 11)->unique();
            $table->string('tin', 15)->unique();

            // Authorized representative
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('middle_initial', 1)->nullable();
            $table->enum('sex', ['Male', 'Female']);
            $table->date('birthday');
            $table->string('email', 150)->unique();
            $table->string('mobile', 13);

            // Sorting center address
            $table->string('region', 150);
            $table->string('province', 150)->nullable();
            $table->string('city', 150);
            $table->string('municipality_psgc_code', 20);
            $table->string('barangay', 150);
            $table->string('house_number', 100);
            $table->string('street', 150);
            $table->string('postal_code', 4);

            // Private document storage paths
            $table->string('valid_id_path');
            $table->string('business_permit_path');

            // Verification and review
            $table->timestamp('email_verified_at')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->timestamps();

            $table->index('municipality_psgc_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_applications');
    }
};
