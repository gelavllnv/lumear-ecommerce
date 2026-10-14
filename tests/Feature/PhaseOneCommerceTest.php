<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerPayoutAccount;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_accepts_cod_only_and_records_inventory_timeline_and_pending_earnings(): void
    {
        [$buyer, $seller, $variant] = $this->marketplaceFixture();
        $cart = Cart::create(['user_id' => $buyer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($buyer)->post(route('checkout'), ['payment_method' => 'wallet'])
            ->assertSessionHasErrors('payment_method');

        $response = $this->actingAs($buyer)->post(route('checkout'), ['payment_method' => 'cod', 'idempotency_key' => 'checkout-test-001']);

        $response->assertRedirect(route('orders'));
        $this->assertDatabaseHas('orders', ['buyer_id' => $buyer->id, 'payment_method' => 'cod', 'total_minor' => 50000]);
        $this->assertDatabaseHas('order_timelines', ['event' => 'order_created', 'status' => 'pending']);
        $this->assertDatabaseHas('inventory_movements', ['product_variant_id' => $variant->id, 'type' => 'sale_reserved', 'quantity_delta' => -2, 'stock_after' => 3]);
        $this->assertDatabaseHas('wallet_accounts', ['seller_id' => $seller->id, 'pending_minor' => 46000]);
        $this->assertSame(3, $variant->fresh()->stock);

        $sellerOrder = SellerOrder::firstOrFail();
        $sellerOrder->update(['status' => 'delivered', 'delivered_at' => now()]);
        app(WalletService::class)->releaseCodEarnings($sellerOrder, $buyer->id, 'COURIER-REMIT-001');
        $this->assertDatabaseHas('wallet_accounts', ['seller_id' => $seller->id, 'pending_minor' => 0, 'available_minor' => 46000]);
        $this->assertDatabaseHas('wallet_journals', ['seller_order_id' => $sellerOrder->id, 'entry_type' => 'cod_sale_released', 'reference' => 'COURIER-REMIT-001']);
    }

    public function test_payout_cannot_exceed_available_seller_balance(): void
    {
        [, $seller] = $this->marketplaceFixture();
        $payoutAccount = SellerPayoutAccount::create(['seller_id' => $seller->id, 'type' => 'bank', 'account_name' => 'Sample Seller', 'account_number' => '1234567890']);
        $wallet = app(WalletService::class)->accountFor($seller);
        $wallet->update(['available_minor' => 15000]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(WalletService::class)->requestPayout($seller, $payoutAccount, 20000);
    }

    public function test_checkout_requires_a_delivery_address_before_creating_an_order(): void
    {
        [$buyer, , $variant] = $this->marketplaceFixture();
        $buyer->addresses()->delete();
        $cart = Cart::create(['user_id' => $buyer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($buyer)->post(route('checkout'), ['payment_method' => 'cod'])
            ->assertRedirect(route('buyer.address.create'))
            ->assertSessionHasErrors('address');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_buyer_can_remove_an_item_from_own_bag(): void
    {
        [$buyer, , $variant] = $this->marketplaceFixture();
        $cart = Cart::create(['user_id' => $buyer->id]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($buyer)->delete(route('cart.destroy', $item))
            ->assertRedirect(route('cart'));

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_buyer_can_open_delivery_address_form_and_update_profile(): void
    {
        [$buyer] = $this->marketplaceFixture();
        $buyer->addresses()->delete();

        $this->actingAs($buyer)->get(route('buyer.address.create'))
            ->assertOk()
            ->assertSee('Add a delivery address');

        $this->actingAs($buyer)->patch(route('account.update'), ['name' => 'Updated Buyer', 'phone' => '09171234567'])
            ->assertRedirect(route('account.show'));

        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'name' => 'Updated Buyer', 'phone' => '09171234567']);
    }

    public function test_login_uses_the_selected_role_only_after_password_authentication(): void
    {
        [$buyer] = $this->marketplaceFixture();

        $this->post(route('login.store'), [
            'email' => $buyer->email,
            'password' => 'password',
            'role' => 'buyer',
        ])->assertRedirect(route('shop'));
    }

    public function test_admin_can_sign_in_from_either_public_login_tab_and_is_sent_to_admin_2fa(): void
    {
        $admin = User::factory()->create(['password' => 'password']);
        $admin->roles()->attach(Role::create(['name' => 'admin']));

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
            'role' => 'seller',
        ])->assertRedirect(route('admin.2fa.setup'));
    }

    public function test_seller_can_progress_an_order_through_shipping_and_delivery(): void
    {
        [$buyer, $seller, $variant] = $this->marketplaceFixture();
        $order = \App\Models\Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'LM-STATUS-001',
            'total_minor' => 25000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_address' => $buyer->addresses()->first()->only(['label', 'recipient', 'phone', 'line1', 'barangay', 'city', 'province', 'postal_code']),
        ]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 25000, 'commission_minor' => 2000, 'status' => 'pending']);
        $sellerOrder->items()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => 'Test Product', 'variant_name' => 'Standard', 'unit_price_minor' => 25000, 'quantity' => 1]);

        foreach (['accepted', 'packed', 'ready_to_ship', 'shipped', 'delivered'] as $status) {
            $this->actingAs($seller->owner)->patch(route('seller.orders.update', $sellerOrder), ['status' => $status])
                ->assertRedirect(route('seller.orders'));
        }

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'delivered']);
        $this->assertNotNull($sellerOrder->fresh()->delivered_at);
    }

    public function test_seller_completion_releases_a_recorded_cod_sale_during_logistics_development(): void
    {
        [$buyer, $seller, $variant] = $this->marketplaceFixture();
        $cart = Cart::create(['user_id' => $buyer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);
        $this->actingAs($buyer)->post(route('checkout'), ['payment_method' => 'cod'])->assertRedirect(route('orders'));
        $sellerOrder = SellerOrder::firstOrFail();

        foreach (['accepted', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'completed'] as $status) {
            $this->actingAs($seller->owner)->patch(route('seller.orders.update', $sellerOrder), ['status' => $status])
                ->assertRedirect(route('seller.orders'));
        }

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'completed']);
        $this->assertDatabaseHas('wallet_accounts', ['seller_id' => $seller->id, 'pending_minor' => 0, 'available_minor' => 23000]);
        $this->assertDatabaseHas('wallet_journals', ['seller_order_id' => $sellerOrder->id, 'entry_type' => 'cod_sale_released']);
    }

    public function test_buyer_can_confirm_delivered_cod_receipt_and_rate_a_purchased_product(): void
    {
        [$buyer, $seller, $variant] = $this->marketplaceFixture();
        $order = \App\Models\Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'LM-RECEIPT-001',
            'total_minor' => 25000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_address' => $buyer->addresses()->first()->only(['label', 'recipient', 'phone', 'line1', 'barangay', 'city', 'province', 'postal_code']),
        ]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 25000, 'commission_minor' => 2000, 'status' => 'delivered', 'delivered_at' => now()]);
        $item = $sellerOrder->items()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => 'Test Product', 'variant_name' => 'Standard', 'unit_price_minor' => 25000, 'quantity' => 1]);

        $this->actingAs($buyer)->post(route('buyer.orders.confirm-receipt', $sellerOrder))
            ->assertRedirect(route('orders'));

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'completed']);
        $this->assertDatabaseHas('order_timelines', ['seller_order_id' => $sellerOrder->id, 'event' => 'buyer_receipt_confirmed']);

        $this->actingAs($buyer)->post(route('buyer.reviews.store', $item), ['rating' => 5, 'body' => 'Exactly as described.'])
            ->assertRedirect(route('orders'));

        $this->assertDatabaseHas('product_reviews', ['buyer_id' => $buyer->id, 'order_item_id' => $item->id, 'product_id' => $variant->product_id, 'rating' => 5]);
    }

    public function test_buyer_cannot_rate_a_product_until_the_cod_parcel_is_received(): void
    {
        [$buyer, $seller, $variant] = $this->marketplaceFixture();
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'reference' => 'LM-EARLY-REVIEW-001', 'total_minor' => 25000, 'payment_method' => 'cod', 'payment_status' => 'pending', 'shipping_address' => $buyer->addresses()->first()->only(['label', 'recipient', 'phone', 'line1', 'barangay', 'city', 'province', 'postal_code'])]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 25000, 'commission_minor' => 2000, 'status' => 'shipped']);
        $item = $sellerOrder->items()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => 'Test Product', 'variant_name' => 'Standard', 'unit_price_minor' => 25000, 'quantity' => 1]);

        $this->actingAs($buyer)->from(route('orders'))->post(route('buyer.reviews.store', $item), ['rating' => 5])
            ->assertRedirect(route('orders'))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    /** @return array{0: User, 1: Seller, 2: ProductVariant} */
    private function marketplaceFixture(): array
    {
        $buyerRole = Role::create(['name' => 'buyer']);
        $sellerRole = Role::create(['name' => 'seller']);
        $buyer = User::factory()->create();
        $buyer->roles()->attach($buyerRole);
        $buyer->addresses()->create(['label' => 'Home', 'recipient' => 'Buyer', 'phone' => '09170000000', 'line1' => '1 Main Street', 'barangay' => 'Barangay Uno', 'city' => 'Los Baños', 'province' => 'Laguna', 'postal_code' => '4030', 'is_default' => true]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->roles()->attach($sellerRole);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Sample Store', 'slug' => 'sample-store', 'status' => 'approved', 'commission_bps' => 800]);
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-product', 'description' => 'A product', 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'TEST-SKU-001', 'name' => 'Standard', 'price_minor' => 25000, 'stock' => 5, 'weight_grams' => 300, 'is_active' => true]);

        return [$buyer, $seller, $variant];
    }
}
