
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Use our temporary Logistics MySQL connection.
     */
    protected $connection = 'logistics_testing';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('logistics_testing')
            ->create('logistics_applications', function (Blueprint $table) {

                $table->id();

                // =========================================
                // STEP 1: COMPANY INFORMATION
                // =========================================

                $table->string('company_name', 150);

                $table->string('sec_number', 50)->unique();

                $table->string('tin', 12)->unique();


                // =========================================
                // STEP 2: REPRESENTATIVE INFORMATION
                // =========================================

                $table->string('first_name', 80);

                $table->string('last_name', 80);

                $table->string('middle_initial', 1)->nullable();

                $table->string('sex', 20);

                $table->date('birthday');

                $table->string('email', 150)->unique();

                $table->string('mobile', 11)->unique();


                // =========================================
                // STEP 3: SORTING CENTER LOCATION
                // =========================================

                $table->string('region', 150);

                $table->string('province', 150)->nullable();

                $table->string('city', 150);

                $table->string('barangay', 150);

                $table->string('house_number', 100);

                $table->string('street', 150);

                $table->string('postal_code', 4);

                /*
                 * One Logistics handler per city/municipality.
                 *
                 * The backend generates a unique city_key
                 * from the region, province and city.
                 *
                 * A pending or approved application
                 * reserves the city.
                 *
                 * If Admin rejects the application,
                 * city_key can be set to NULL to release it.
                 */

                $table->string('city_key', 64)
                    ->nullable()
                    ->unique();


                // =========================================
                // STEP 4: REQUIRED DOCUMENTS
                // =========================================

                // Store private file paths, not actual images.
                $table->string('valid_id_path');

                $table->string('business_permit_path');


                // =========================================
                // STEP 5: ACCOUNT SECURITY
                // =========================================

                // Password will be hashed by Laravel.
                $table->string('password');


                // =========================================
                // STEP 6: EMAIL OTP VERIFICATION
                // =========================================

                /*
                 * OTP codes are temporarily stored
                 * in Laravel sessions during registration.
                 *
                 * Only successful verification is recorded
                 * permanently in this table.
                 */

                $table->timestamp('email_verified_at')
                    ->nullable();


                // =========================================
                // STEP 7: APPLICATION STATUS
                // =========================================

                /*
                 * pending  = Waiting for Admin approval
                 * approved = Approved by Admin
                 * rejected = Rejected by Admin
                 */

                $table->string('status', 20)
                    ->default('pending');


                // =========================================
                // CREATED / UPDATED DATES
                // =========================================

                $table->timestamps();

            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('logistics_testing')
            ->dropIfExists('logistics_applications');
    }
};
