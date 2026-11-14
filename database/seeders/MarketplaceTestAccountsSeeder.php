<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerAgreementAcceptance;
use App\Models\SellerKycSubmission;
use App\Models\SellerOnboardingStep;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

/**
 * Creates four complete local demonstration accounts without changing
 * instructor-provided users or marketplace data. It is safe to run again.
 */
class MarketplaceTestAccountsSeeder extends Seeder
{
    private const PASSWORD = 'LumearDemo!2026';

    public function run(): void
    {
        DB::transaction(function (): void {
            $buyerRole = Role::firstOrCreate(['name' => 'buyer']);
            $sellerRole = Role::firstOrCreate(['name' => 'seller']);

            foreach ($this->buyers() as $details) {
                $buyer = User::updateOrCreate(
                    ['email' => $details['email']],
                    [
                        'name' => $details['name'],
                        'phone' => $details['phone'],
                        'email_verified_at' => now(),
                        'password' => Hash::make(self::PASSWORD),
                        'is_suspended' => false,
                    ],
                );
                $buyer->roles()->syncWithoutDetaching([$buyerRole->id]);

                Address::updateOrCreate(
                    ['user_id' => $buyer->id, 'label' => 'Home delivery address'],
                    [
                        'recipient' => $details['name'],
                        'phone' => $details['phone'],
                        'line1' => $details['line1'],
                        'barangay' => $details['barangay'],
                        'city' => $details['city'],
                        'province' => $details['province'],
                        'postal_code' => $details['postal_code'],
                        'is_default' => true,
                    ],
                );
            }

            foreach ($this->sellers() as $details) {
                $category = Category::firstOrCreate(
                    ['slug' => $details['category_slug']],
                    ['name' => $details['category_name'], 'is_active' => true],
                );

                $owner = User::updateOrCreate(
                    ['email' => $details['email']],
                    [
                        'name' => $details['owner_name'],
                        'phone' => $details['phone'],
                        'email_verified_at' => now(),
                        'password' => Hash::make(self::PASSWORD),
                        'is_suspended' => false,
                    ],
                );
                $owner->roles()->syncWithoutDetaching([$sellerRole->id]);

                $pickupAddress = Address::updateOrCreate(
                    ['user_id' => $owner->id, 'label' => 'Shop pickup address'],
                    [
                        'recipient' => $details['owner_name'],
                        'phone' => $details['phone'],
                        'line1' => $details['line1'],
                        'barangay' => $details['barangay'],
                        'city' => $details['city'],
                        'province' => $details['province'],
                        'postal_code' => $details['postal_code'],
                        'is_default' => true,
                    ],
                );

                $seller = Seller::updateOrCreate(
                    ['user_id' => $owner->id],
                    [
                        'name' => $details['shop_name'],
                        'slug' => $details['slug'],
                        'description' => $details['description'],
                        'category_id' => $category->id,
                        'pickup_address_id' => $pickupAddress->id,
                        'status' => 'approved',
                        'rejection_reason' => null,
                        'commission_bps' => 800,
                    ],
                );

                SellerProfile::updateOrCreate(
                    ['seller_id' => $seller->id],
                    [
                        'business_name' => $details['shop_name'],
                        'business_type' => 'individual',
                        'tax_number' => $details['tin'],
                        'business_hours' => ['Monday-Saturday' => '09:00-18:00'],
                        'shipping_policy' => 'Orders are prepared within 1-2 business days.',
                        'return_policy' => 'Contact the shop within 7 days for eligible return requests.',
                    ],
                );

                SellerKycSubmission::updateOrCreate(
                    ['seller_id' => $seller->id, 'tax_number' => $details['tin']],
                    [
                        'business_type' => 'individual',
                        'government_id_path' => null,
                        'business_permit_path' => null,
                        'business_registration_path' => null,
                        'bir_certificate_path' => null,
                        'selfie_path' => null,
                        'status' => 'approved',
                        'rejection_reason' => null,
                        'submitted_at' => now(),
                        'reviewed_at' => now(),
                    ],
                );

                DB::table('seller_payout_accounts')->updateOrInsert(
                    ['seller_id' => $seller->id, 'is_default' => true],
                    [
                        'type' => 'e_wallet',
                        'account_name' => $details['owner_name'],
                        'account_number' => Crypt::encryptString($details['payout_number']),
                        'verified_at' => now(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );

                foreach (['store_profile', 'kyc_submitted', 'payout_details', 'seller_agreement'] as $step) {
                    SellerOnboardingStep::updateOrCreate(
                        ['seller_id' => $seller->id, 'step' => $step],
                        ['completed_at' => now()],
                    );
                }
                SellerOnboardingStep::updateOrCreate(
                    ['seller_id' => $seller->id, 'step' => 'first_product'],
                    ['completed_at' => null],
                );

                SellerAgreementAcceptance::updateOrCreate(
                    ['seller_id' => $seller->id, 'version' => 'seller-terms-1.0'],
                    ['ip_address' => '127.0.0.1', 'user_agent' => 'Lumear test-account seeder', 'accepted_at' => now()],
                );
                WalletAccount::firstOrCreate(['seller_id' => $seller->id]);
            }
        });
    }

    /** @return array<int, array<string, string>> */
    private function buyers(): array
    {
        return [
            ['name' => 'Maya Santos', 'email' => 'buyer.maya@lumear.test', 'phone' => '09171234501', 'line1' => '18 Maharlika Street', 'barangay' => 'Anos', 'city' => 'Los Baños', 'province' => 'Laguna', 'postal_code' => '4030'],
            ['name' => 'Carlos Reyes', 'email' => 'buyer.carlos@lumear.test', 'phone' => '09171234502', 'line1' => '42 Rizal Avenue', 'barangay' => 'Poblacion', 'city' => 'Santa Cruz', 'province' => 'Laguna', 'postal_code' => '4009'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function sellers(): array
    {
        return [
            ['owner_name' => 'Nina Cruz', 'email' => 'seller.nina@lumear.test', 'phone' => '09171234503', 'shop_name' => 'Nina Threads', 'slug' => 'nina-threads-demo', 'description' => 'Curated everyday clothing and accessories.', 'category_name' => 'Fashion', 'category_slug' => 'fashion', 'tin' => '123-456-789-001', 'payout_number' => '09171234503', 'line1' => '12 Lopez Avenue', 'barangay' => 'Batong Malake', 'city' => 'Los Baños', 'province' => 'Laguna', 'postal_code' => '4030'],
            ['owner_name' => 'Marco Villanueva', 'email' => 'seller.marco@lumear.test', 'phone' => '09171234504', 'shop_name' => 'Marco Home Finds', 'slug' => 'marco-home-finds-demo', 'description' => 'Useful home and living essentials for local households.', 'category_name' => 'Home & Living', 'category_slug' => 'home-living', 'tin' => '123-456-789-002', 'payout_number' => '09171234504', 'line1' => '9 Quezon Street', 'barangay' => 'Santo Angel Central', 'city' => 'Santa Cruz', 'province' => 'Laguna', 'postal_code' => '4009'],
        ];
    }
}
