<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || config('database.default') !== 'sqlite' || basename(config('database.connections.sqlite.database')) !== 'integration.sqlite') {
            throw new \RuntimeException('Integration sample accounts are restricted to the local integration.sqlite database.');
        }
        DB::transaction(function (): void {
            $this->call(DatabaseSeeder::class);
            $buyer = $this->account('buyer', 'buyer');
            $buyer->addresses()->firstOrCreate(['label' => 'Home'], ['recipient' => 'Local Test Buyer', 'phone' => '09170000000', 'line1' => '123 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Victoria', 'province' => 'Laguna', 'postal_code' => '4011', 'is_default' => true]);
            $owner = $this->account('seller', 'seller');
            $pickupAddress = $owner->addresses()->firstOrCreate(['label' => 'Shop pickup'], ['recipient' => 'Local Test Seller', 'phone' => '09170000001', 'line1' => '456 Shop Street', 'barangay' => 'Test Barangay', 'city' => 'Victoria', 'province' => 'Laguna', 'postal_code' => '4011', 'is_default' => true]);
            $category = Category::firstOrCreate(['slug' => 'local-integration'], ['name' => 'Local Integration']);
            $seller = Seller::firstOrCreate(['user_id' => $owner->id], ['name' => 'Local Test Shop', 'slug' => 'local-test-shop', 'status' => 'approved', 'commission_bps' => 1000, 'category_id' => $category->id, 'pickup_address_id' => $pickupAddress->id]);
            $seller->profile()->firstOrCreate([], ['description' => 'Local test storefront.']);
            $product = Product::firstOrCreate(['slug' => 'integration-shirt'], ['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Integration Test Shirt', 'description' => 'Sample product for testing the shared order and delivery workflow.', 'is_active' => true]);
            ProductVariant::firstOrCreate(['sku' => 'LOCAL-SHIRT-001'], ['product_id' => $product->id, 'name' => 'Default', 'price_minor' => 25000, 'stock' => 100, 'weight_grams' => 500, 'is_active' => true]);
            $origin = LogisticsProvider::firstOrCreate(['slug' => 'local-origin'], ['user_id' => $this->account('logistics', 'logistics')->id, 'name' => 'Local Origin Hub', 'contact_phone' => '09170000002', 'status' => 'approved']);
            $destination = LogisticsProvider::firstOrCreate(['slug' => 'local-destination'], ['user_id' => $this->account('destination', 'logistics')->id, 'name' => 'Local Destination Hub', 'contact_phone' => '09170000003', 'status' => 'approved']);
            foreach ([['van', 'Van', 'Seller Pickup', $origin], ['truck', 'Truck', 'Long-distance Handover', $origin], ['rider', 'Motorcycle', 'Delivery', $destination], ['origin-rider', 'Motorcycle', 'Delivery', $origin], ['van-delivery', 'Van', 'Delivery', $destination], ['truck-delivery', 'Truck', 'Delivery', $destination]] as [$name, $vehicle, $job, $provider]) {
                $user = $this->account($name, null);
                Rider::firstOrCreate(['user_id' => $user->id], ['logistics_provider_id' => $provider->id, 'vehicle_type' => $vehicle, 'job_type' => $job, 'plate_no' => 'LOCAL-'.Str::upper($name), 'is_active' => true, 'management_status' => 'Available']);
            }
        });
        $this->command?->info('Local accounts: buyer, seller, logistics, destination, van, truck, rider, origin-rider, van-delivery, truck-delivery @lumear.test');
        $this->command?->info('Local test password: LumearLocal2026!');
    }

    private function account(string $name, ?string $role): User
    {
        $user = User::firstOrCreate(['email' => $name.'@lumear.test'], ['name' => 'Local '.ucwords(str_replace('-', ' ', $name)), 'password' => 'LumearLocal2026!', 'phone' => '09170000000', 'is_suspended' => false]);
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        if ($role) {
            $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);
        }

        return $user;
    }
}
