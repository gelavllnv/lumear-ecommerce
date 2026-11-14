<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function token(User $user, string $role): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Password123!', 'role' => $role])->assertOk()->json('token');
    }

    private function rider(LogisticsProvider $provider, string $vehicle, string $job): Rider
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        return Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => $vehicle, 'job_type' => $job, 'plate_no' => 'TEST-123', 'is_active' => true, 'management_status' => 'Available']);
    }

    private function riderToken(Rider $rider): string
    {
        return $this->postJson('/api/rider/auth/login', ['email' => $rider->user->email, 'password' => 'Password123!'])->assertOk()->json('token');
    }

    public function test_shared_order_moves_from_apps_through_seller_pickup_hub_transfer_and_customer_delivery(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $buyer = $this->account('buyer');
        $address = $buyer->addresses()->create(['label' => 'Home', 'recipient' => 'Parcel Recipient', 'phone' => '09170000000', 'line1' => '123 Main Street', 'barangay' => 'Uno', 'city' => 'Victoria', 'province' => 'Laguna', 'postal_code' => '4011', 'is_default' => true]);
        $sellerUser = $this->account('seller');
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Test Shop', 'slug' => 'test-shop', 'status' => 'approved', 'commission_bps' => 800, 'pickup_address_id' => $address->id]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => Category::create(['name' => 'Clothes', 'slug' => 'clothes'])->id, 'name' => 'Test Shirt', 'slug' => 'test-shirt', 'description' => 'Test product for integration.', 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Default', 'sku' => 'SHIRT-1', 'price_minor' => 25000, 'stock' => 4, 'is_active' => true]);
        $origin = LogisticsProvider::create(['user_id' => $this->account('logistics')->id, 'name' => 'Origin Hub', 'slug' => 'origin', 'contact_phone' => '09170000001', 'status' => 'approved']);
        $destination = LogisticsProvider::create(['user_id' => $this->account('logistics')->id, 'name' => 'Destination Hub', 'slug' => 'destination', 'contact_phone' => '09170000002', 'status' => 'approved']);
        $van = $this->rider($origin, 'Van', 'Seller Pickup');
        $truck = $this->rider($origin, 'Truck', 'Long-distance Handover');
        $delivery = $this->rider($destination, 'Motorcycle', 'Delivery');
        $buyerToken = $this->token($buyer, 'buyer');
        $sellerToken = $this->token($sellerUser, 'seller');
        $originToken = $this->token($origin->user, 'logistics');
        $destinationToken = $this->token($destination->user, 'logistics');
        $vanToken = $this->riderToken($van);
        $truckToken = $this->riderToken($truck);
        $deliveryToken = $this->riderToken($delivery);

        $this->withToken($buyerToken)->postJson('/api/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1])->assertOk();
        $quote = $this->postJson('/api/checkout/quote', ['address_id' => $address->id])->assertOk()->assertJsonPath('total', 30000)->json();
        $checkout = ['address_id' => $address->id, 'payment_method' => 'COD', 'idempotency_key' => 'mobile-order-1', 'expected_quote' => $quote];
        $this->postJson('/api/checkout', $checkout)->assertCreated();
        $this->postJson('/api/checkout', $checkout)->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $order = SellerOrder::firstOrFail();
        $this->getJson('/api/buyer/workspace')->assertOk()->assertJsonPath('orders.0.id', (string) $order->id)->assertJsonPath('orders.0.timeline.0.date', $order->timeline->first()->created_at->timestamp);

        $this->withToken($sellerToken)->postJson('/api/seller/orders/'.$order->id.'/prepare', ['logistics_provider_id' => $origin->id])->assertOk();
        $shipment = Shipment::firstOrFail();
        $this->assertSame(30000, $shipment->cod_amount_minor);
        $pickupId = $this->withToken($originToken)->postJson('/api/logistics/tasks', ['shipment_id' => $shipment->id, 'rider_id' => $van->id, 'job_type' => 'Seller Pickup'])->assertCreated()->json('id');
        foreach (['heading', 'arrived', 'collected'] as $status) {
            $this->withToken($vanToken)->patchJson('/api/rider/tasks/'.$pickupId, ['status' => $status])->assertOk();
        }
        $this->patchJson('/api/rider/tasks/'.$pickupId, ['status' => 'seller_confirmed'])->assertForbidden();
        $this->withToken($sellerToken)->patchJson('/api/seller/tasks/'.$pickupId, ['status' => 'seller_confirmed'])->assertOk();
        $this->withToken($originToken)->patchJson('/api/logistics/tasks/'.$pickupId, ['status' => 'completed'])->assertOk();

        $transferId = $this->postJson('/api/logistics/tasks', ['shipment_id' => $shipment->id, 'rider_id' => $truck->id, 'job_type' => 'Long-distance Handover', 'destination_provider_id' => $destination->id])->assertCreated()->json('id');
        foreach (['heading', 'in_transit', 'arrived'] as $status) {
            $this->withToken($truckToken)->patchJson('/api/rider/tasks/'.$transferId, ['status' => $status])->assertOk();
        }
        $this->withToken($originToken)->patchJson('/api/logistics/tasks/'.$transferId, ['status' => 'completed'])->assertForbidden();
        $this->withToken($destinationToken)->patchJson('/api/logistics/tasks/'.$transferId, ['status' => 'completed'])->assertOk();
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'logistics_provider_id' => $destination->id, 'status' => 'picked_up']);
        DB::table('logistics_sorting')->where('shipment_id', $shipment->id)->update(['stage' => 'sorted', 'verified_at' => now()]);
        $this->postJson('/api/logistics/delivery-assignment', ['rider_id' => $delivery->id, 'shipment_ids' => [$shipment->id]])->assertOk();
        $this->withToken($deliveryToken)->getJson('/api/rider/shipments')->assertOk()->assertJsonPath('shipments.0.buyer_name', 'Parcel Recipient');
        $this->patchJson('/api/rider/shipments/'.$shipment->id.'/status', ['status' => 'delivered'])->assertUnprocessable();
        $this->patchJson('/api/rider/shipments/'.$shipment->id.'/status', ['status' => 'out_for_delivery'])->assertOk();
        $this->patchJson('/api/rider/shipments/'.$shipment->id.'/status', ['status' => 'delivered'])->assertOk();
        $this->patchJson('/api/rider/shipments/'.$shipment->id.'/status', ['status' => 'delivered'])->assertOk();
        $this->assertDatabaseHas('seller_orders', ['id' => $order->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'cod_collected' => true]);
        $this->assertDatabaseHas('order_timelines', ['seller_order_id' => $order->id, 'event' => 'rider_delivered']);
        $this->withToken($buyerToken)->postJson('/api/orders/'.$order->id.'/receive')->assertOk();
        $this->assertDatabaseHas('seller_orders', ['id' => $order->id, 'status' => 'completed']);
        $this->assertDatabaseHas('wallet_accounts', ['seller_id' => $seller->id, 'available_minor' => 0]);
    }

    public function test_mobile_tokens_are_role_scoped_and_recheck_account_suspension(): void
    {
        $buyer = $this->account('buyer');
        $token = $this->token($buyer, 'buyer');
        $this->withToken($token)->getJson('/api/seller/workspace')->assertUnauthorized();
        $this->getJson('/api/logistics/workspace')->assertUnauthorized();
        $buyer->update(['is_suspended' => true]);
        $this->getJson('/api/buyer/workspace')->assertForbidden();
    }

    public function test_offline_rider_can_sign_in_and_go_online_but_suspension_blocks_existing_token(): void
    {
        $provider = LogisticsProvider::create(['user_id' => $this->account('logistics')->id, 'name' => 'Hub', 'slug' => 'hub', 'contact_phone' => '09170000000', 'status' => 'approved']);
        $rider = $this->rider($provider, 'Truck', 'Delivery');
        $token = $this->riderToken($rider);
        $this->withToken($token)->patchJson('/api/rider/availability', ['available' => false])->assertOk();
        $token = $this->riderToken($rider);
        $this->withToken($token)->patchJson('/api/rider/availability', ['available' => true])->assertOk();
        $rider->update(['management_status' => 'Suspended']);
        $this->getJson('/api/rider/me')->assertForbidden();
    }

    public function test_invalid_vehicle_job_combinations_are_rejected_at_registration(): void
    {
        foreach ([['Motorcycle', 'Seller Pickup'], ['Van', 'Long-distance Handover'], ['Truck', 'Seller Pickup']] as [$vehicle, $job]) {
            $this->postJson('/api/rider/auth/register', ['vehicle_type' => $vehicle, 'job_type' => $job])->assertUnprocessable()->assertJsonValidationErrors('job_type');
        }
    }
}
