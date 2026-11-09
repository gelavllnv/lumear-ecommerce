<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\BundleDeal;
use App\Models\BuyerSellerVoucher;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\FlashSale;
use App\Models\InventoryMovement;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTimeline;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewReply;
use App\Models\ProductSale;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SavedProduct;
use App\Models\Seller;
use App\Models\SellerAgreementAcceptance;
use App\Models\SellerKycSubmission;
use App\Models\SellerOnboardingStep;
use App\Models\SellerOrder;
use App\Models\SellerPayoutAccount;
use App\Models\SellerProfile;
use App\Models\SellerStoreCategory;
use App\Models\SellerVoucher;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a complete, repeatable local marketplace demo without changing
 * instructor-provided or real marketplace records. Every seeded record is
 * scoped to the four @lumear.test users and DEMO-* identifiers below.
 */
class MarketplaceTestAccountsSeeder extends Seeder
{
    public const PASSWORD = 'LumearDemo!2026';

    public function run(): void
    {
        DB::transaction(function (): void {
            $buyerRole = Role::firstOrCreate(['name' => 'buyer']);
            $sellerRole = Role::firstOrCreate(['name' => 'seller']);

            $buyers = collect($this->buyers())->mapWithKeys(function (array $details) use ($buyerRole): array {
                $buyer = User::updateOrCreate(
                    ['email' => $details['email']],
                    [
                        'name' => $details['name'],
                        'phone' => $details['phone'],
                        'date_of_birth' => $details['date_of_birth'],
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

                return [$details['email'] => $buyer];
            });

            $sellers = collect($this->sellers())->mapWithKeys(function (array $details) use ($sellerRole): array {
                $seller = $this->seedSeller($details, $sellerRole);

                return [$details['email'] => $seller];
            });

            $products = $this->seedCatalog($sellers);
            $this->seedBuyerWorkspace($buyers, $products);
        });
    }

    private function seedSeller(array $details, Role $sellerRole): Seller
    {
        $category = Category::firstOrCreate(
            ['slug' => $details['category_slug']],
            ['name' => $details['category_name'], 'is_active' => true],
        );

        $owner = User::updateOrCreate(
            ['email' => $details['email']],
            [
                'name' => $details['owner_name'],
                'phone' => $details['phone'],
                'date_of_birth' => $details['date_of_birth'],
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
                'is_on_vacation' => false,
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

        SellerPayoutAccount::updateOrCreate(
            ['seller_id' => $seller->id, 'is_default' => true],
            [
                'type' => 'e_wallet',
                'account_name' => $details['owner_name'],
                'account_number' => $details['payout_number'],
                'verified_at' => now(),
            ],
        );

        foreach (['store_profile', 'kyc_submitted', 'payout_details', 'seller_agreement'] as $step) {
            SellerOnboardingStep::updateOrCreate(
                ['seller_id' => $seller->id, 'step' => $step],
                ['completed_at' => now()],
            );
        }
        SellerOnboardingStep::updateOrCreate(['seller_id' => $seller->id, 'step' => 'first_product'], ['completed_at' => now()]);

        SellerAgreementAcceptance::updateOrCreate(
            ['seller_id' => $seller->id, 'version' => 'seller-terms-1.0'],
            ['ip_address' => '127.0.0.1', 'user_agent' => 'Lumear demo account seeder', 'accepted_at' => now()],
        );
        WalletAccount::firstOrCreate(['seller_id' => $seller->id]);

        return $seller;
    }

    /** @param \Illuminate\Support\Collection<string, Seller> $sellers */
    private function seedCatalog($sellers): \Illuminate\Support\Collection
    {
        $products = collect();

        foreach ($this->catalog() as $details) {
            /** @var Seller $seller */
            $seller = $sellers->get($details['seller_email']);
            $storeCategory = SellerStoreCategory::firstOrCreate(
                ['seller_id' => $seller->id, 'slug' => $details['store_category_slug']],
                ['name' => $details['store_category'], 'is_visible' => true, 'position' => $details['position']],
            );

            $product = Product::withTrashed()->firstOrNew(['slug' => $details['slug']]);
            if ($product->trashed()) {
                $product->restore();
            }
            $product->fill([
                'seller_id' => $seller->id,
                'category_id' => $seller->category_id,
                'name' => $details['name'],
                'description' => $details['description'],
                'is_active' => true,
                'publication_status' => 'active',
                'moderation_status' => 'active',
                'moderation_reason' => null,
                'length_mm' => $details['length_mm'],
                'width_mm' => $details['width_mm'],
                'height_mm' => $details['height_mm'],
            ]);
            $product->slug = $details['slug'];
            $product->save();
            $product->storeCategories()->syncWithoutDetaching([$storeCategory->id]);

            $variant = ProductVariant::withTrashed()->firstOrNew(['sku' => $details['sku']]);
            if ($variant->trashed()) {
                $variant->restore();
            }
            $isNew = ! $variant->exists;
            $variant->fill([
                'product_id' => $product->id,
                'name' => $details['variant_name'],
                'options' => $details['options'],
                'price_minor' => $details['price_minor'],
                'stock' => $details['stock'],
                'weight_grams' => $details['weight_grams'],
                'is_active' => true,
            ]);
            $variant->sku = $details['sku'];
            $variant->save();

            if ($isNew && ! InventoryMovement::where('product_variant_id', $variant->id)->where('type', 'demo_initial_stock')->exists()) {
                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'actor_id' => $seller->user_id,
                    'type' => 'demo_initial_stock',
                    'quantity_delta' => $details['stock'],
                    'stock_after' => $details['stock'],
                    'reference' => $product->slug,
                    'note' => 'Initial stock for the Lumear demo marketplace.',
                ]);
            }

            $products->put($details['key'], ['product' => $product, 'variant' => $variant, 'seller' => $seller]);
        }

        $this->seedPromotions($products);

        return $products;
    }

    /** @param \Illuminate\Support\Collection<string, array{product: Product, variant: ProductVariant, seller: Seller}> $products */
    private function seedPromotions($products): void
    {
        $now = now();
        $tote = $products->get('nina-tote');
        $pouch = $products->get('nina-pouch');
        $lamp = $products->get('marco-lamp');

        $voucher = SellerVoucher::updateOrCreate(
            ['code' => 'NINA10'],
            [
                'seller_id' => $tote['seller']->id,
                'discount_type' => 'percentage',
                'discount_minor' => null,
                'discount_percent' => 10,
                'discount_cap_minor' => 20000,
                'minimum_spend_minor' => 80000,
                'total_usage_limit' => 50,
                'per_buyer_limit' => 1,
                'used_count' => 0,
                'is_claimable' => true,
                'status' => 'ongoing',
                'is_paused' => false,
                'archived_at' => null,
                'starts_at' => $now->copy()->subDay(),
                'ends_at' => $now->copy()->addDays(14),
            ],
        );
        $voucher->products()->sync([$tote['product']->id, $pouch['product']->id]);

        FlashSale::updateOrCreate(
            ['seller_id' => $tote['seller']->id, 'product_id' => $tote['product']->id],
            [
                'flash_price_minor' => 99900,
                'quantity_limit' => 12,
                'quantity_sold' => 2,
                'status' => 'running',
                'is_paused' => false,
                'archived_at' => null,
                'starts_at' => $now->copy()->subHour(),
                'ends_at' => $now->copy()->addHours(23),
            ],
        );

        ProductSale::updateOrCreate(
            ['product_id' => $lamp['product']->id, 'discount_type' => 'percentage'],
            [
                'discount_type' => 'percentage',
                'discount_percent' => 15,
                'sale_price_minor' => null,
                'status' => 'running',
                'is_paused' => false,
                'archived_at' => null,
                'ends_at' => $now->copy()->addDays(7),
            ],
        );

        $bundle = BundleDeal::updateOrCreate(
            ['seller_id' => $tote['seller']->id, 'name' => 'Everyday carry set'],
            [
                'type' => 'percentage_bundle',
                'buy_quantity' => 2,
                'get_quantity' => 0,
                'discount_percent' => 12,
                'is_active' => true,
                'archived_at' => null,
                'starts_at' => $now->copy()->subDay(),
                'ends_at' => $now->copy()->addDays(14),
            ],
        );
        $bundle->qualifierProducts()->sync([$tote['product']->id, $pouch['product']->id]);
        $bundle->rewardProducts()->sync([]);
    }

    /** @param \Illuminate\Support\Collection<string, User> $buyers
     *  @param \Illuminate\Support\Collection<string, array{product: Product, variant: ProductVariant, seller: Seller}> $products
     */
    private function seedBuyerWorkspace($buyers, $products): void
    {
        /** @var User $maya */
        $maya = $buyers->get('buyer.maya@lumear.test');
        /** @var User $carlos */
        $carlos = $buyers->get('buyer.carlos@lumear.test');
        $tote = $products->get('nina-tote');
        $pouch = $products->get('nina-pouch');
        $lamp = $products->get('marco-lamp');

        $cart = Cart::firstOrCreate(['user_id' => $maya->id]);
        CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_variant_id' => $lamp['variant']->id],
            ['quantity' => 1, 'selected' => true],
        );
        SavedProduct::firstOrCreate(['buyer_id' => $maya->id, 'product_id' => $tote['product']->id]);
        SavedProduct::firstOrCreate(['buyer_id' => $maya->id, 'product_id' => $lamp['product']->id]);

        $voucher = SellerVoucher::where('code', 'NINA10')->firstOrFail();
        BuyerSellerVoucher::updateOrCreate(
            ['buyer_id' => $maya->id, 'seller_voucher_id' => $voucher->id],
            ['use_count' => 0, 'claimed_at' => now(), 'removed_at' => null],
        );

        $completed = $this->seedOrder(
            buyer: $maya,
            item: $tote,
            reference: 'DEMO-MAYA-COMPLETE',
            sellerStatus: 'completed',
            paymentStatus: 'paid',
            quantity: 2,
            promotionDiscountMinor: 10000,
            note: 'Completed demo order with a buyer review.',
        );
        $this->seedOrder(
            buyer: $maya,
            item: $lamp,
            reference: 'DEMO-MAYA-PACKED',
            sellerStatus: 'packed',
            paymentStatus: 'pending',
            quantity: 1,
            promotionDiscountMinor: 0,
            note: 'Packed demo order. Cancellation is correctly unavailable after packing.',
        );
        $this->seedOrder(
            buyer: $carlos,
            item: $pouch,
            reference: 'DEMO-CARLOS-READY',
            sellerStatus: 'ready_to_ship',
            paymentStatus: 'pending',
            quantity: 1,
            promotionDiscountMinor: 0,
            note: 'Ready-for-pickup demo order for seller fulfillment testing.',
        );

        $review = ProductReview::updateOrCreate(
            ['buyer_id' => $maya->id, 'order_item_id' => $completed['item']->id],
            [
                'seller_order_id' => $completed['seller_order']->id,
                'product_id' => $tote['product']->id,
                'rating' => 5,
                'body' => 'Practical, well made, and exactly as described.',
            ],
        );
        ProductReviewReply::updateOrCreate(
            ['product_review_id' => $review->id],
            ['seller_id' => $tote['seller']->id, 'body' => 'Thank you, Maya — we are glad it is useful!'],
        );

        $this->seedConversation($maya, $tote['seller'], $tote['product']);
        $this->seedConversation($carlos, $lamp['seller'], $lamp['product']);
    }

    /** @param array{product: Product, variant: ProductVariant, seller: Seller} $item
     *  @return array{seller_order: SellerOrder, item: OrderItem}
     */
    private function seedOrder(User $buyer, array $item, string $reference, string $sellerStatus, string $paymentStatus, int $quantity, int $promotionDiscountMinor, string $note): array
    {
        $shippingAddress = Address::where('user_id', $buyer->id)->where('is_default', true)->firstOrFail();
        $subtotal = $item['variant']->price_minor * $quantity;
        $order = Order::updateOrCreate(
            ['reference' => $reference],
            [
                'buyer_id' => $buyer->id,
                'total_minor' => $subtotal - $promotionDiscountMinor,
                'payment_method' => 'cod',
                'payment_status' => $paymentStatus,
                'shipping_address' => [
                    'recipient' => $shippingAddress->recipient,
                    'phone' => $shippingAddress->phone,
                    'line1' => $shippingAddress->line1,
                    'barangay' => $shippingAddress->barangay,
                    'city' => $shippingAddress->city,
                    'province' => $shippingAddress->province,
                    'postal_code' => $shippingAddress->postal_code,
                ],
            ],
        );
        $sellerOrder = SellerOrder::updateOrCreate(
            ['order_id' => $order->id, 'seller_id' => $item['seller']->id],
            [
                'subtotal_minor' => $subtotal,
                'shipping_fee_minor' => 0,
                'commission_minor' => (int) round($subtotal * 0.08),
                'status' => $sellerStatus,
                'delivered_at' => in_array($sellerStatus, ['delivered', 'completed'], true) ? now()->subDays(2) : null,
                'note' => $note,
            ],
        );
        $orderItem = OrderItem::updateOrCreate(
            ['seller_order_id' => $sellerOrder->id, 'product_variant_id' => $item['variant']->id],
            [
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'variant_name' => $item['variant']->name,
                'unit_price_minor' => $item['variant']->price_minor,
                'quantity' => $quantity,
                'promotion_discount_minor' => $promotionDiscountMinor,
            ],
        );
        Payment::updateOrCreate(
            ['order_id' => $order->id, 'method' => 'cod'],
            ['amount_minor' => $order->total_minor, 'status' => $paymentStatus, 'provider_ref' => 'DEMO-'.$reference],
        );

        foreach ($this->timelineFor($sellerStatus, $note) as $entry) {
            OrderTimeline::updateOrCreate(
                ['seller_order_id' => $sellerOrder->id, 'event' => $entry['event']],
                [
                    'order_id' => $order->id,
                    'actor_id' => $entry['actor'] === 'buyer' ? $buyer->id : $item['seller']->user_id,
                    'status' => $entry['status'],
                    'note' => $entry['note'],
                    'metadata' => ['demo' => true],
                ],
            );
        }

        return ['seller_order' => $sellerOrder, 'item' => $orderItem];
    }

    /** @return array<int, array{event: string, status: string, actor: string, note: string}> */
    private function timelineFor(string $status, string $note): array
    {
        $timeline = [
            ['event' => 'order_created', 'status' => 'pending', 'actor' => 'buyer', 'note' => 'Demo COD order created.'],
            ['event' => 'seller_order_created', 'status' => 'pending', 'actor' => 'buyer', 'note' => 'Awaiting seller confirmation.'],
        ];
        $states = [
            'packed' => [['event' => 'seller_accepted', 'status' => 'accepted', 'note' => 'Seller accepted the order.'], ['event' => 'seller_packed', 'status' => 'packed', 'note' => $note]],
            'ready_to_ship' => [['event' => 'seller_accepted', 'status' => 'accepted', 'note' => 'Seller accepted the order.'], ['event' => 'seller_packed', 'status' => 'packed', 'note' => 'Seller packed the order.'], ['event' => 'pickup_requested', 'status' => 'ready_to_ship', 'note' => $note]],
            'completed' => [['event' => 'seller_accepted', 'status' => 'accepted', 'note' => 'Seller accepted the order.'], ['event' => 'seller_packed', 'status' => 'packed', 'note' => 'Seller packed the order.'], ['event' => 'delivery_confirmed', 'status' => 'delivered', 'note' => 'Parcel delivered to buyer.'], ['event' => 'buyer_receipt_confirmed', 'status' => 'completed', 'note' => $note]],
        ];

        foreach ($states[$status] ?? [] as $state) {
            $timeline[] = ['actor' => 'seller'] + $state;
        }

        return $timeline;
    }

    private function seedConversation(User $buyer, Seller $seller, Product $product): void
    {
        $conversation = Conversation::firstOrCreate(
            ['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'rider_id' => null, 'shipment_id' => null],
            ['last_message_at' => now()->subMinutes(5)],
        );
        $buyerMessage = Message::firstOrCreate(
            ['conversation_id' => $conversation->id, 'sender_id' => $buyer->id, 'body' => 'Hi! Is '.$product->name.' still available?'],
            ['product_id' => $product->id, 'read_at' => now()],
        );
        Message::firstOrCreate(
            ['conversation_id' => $conversation->id, 'sender_id' => $seller->user_id, 'body' => 'Yes, it is available and ready to prepare. Thank you for asking!'],
            ['product_id' => $buyerMessage->product_id, 'read_at' => null],
        );
        $conversation->update(['last_message_at' => now()->subMinutes(2)]);
    }

    /** @return array<int, array<string, string>> */
    private function buyers(): array
    {
        return [
            ['name' => 'Maya Santos', 'email' => 'buyer.maya@lumear.test', 'phone' => '09171234501', 'date_of_birth' => '1998-04-14', 'line1' => '18 Maharlika Street', 'barangay' => 'Anos', 'city' => 'Los Baños', 'province' => 'Laguna', 'postal_code' => '4030'],
            ['name' => 'Carlos Reyes', 'email' => 'buyer.carlos@lumear.test', 'phone' => '09171234502', 'date_of_birth' => '1994-09-28', 'line1' => '42 Rizal Avenue', 'barangay' => 'Poblacion', 'city' => 'Santa Cruz', 'province' => 'Laguna', 'postal_code' => '4009'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function sellers(): array
    {
        return [
            ['owner_name' => 'Nina Cruz', 'email' => 'seller.nina@lumear.test', 'phone' => '09171234503', 'date_of_birth' => '1992-02-08', 'shop_name' => 'Nina Threads', 'slug' => 'nina-threads-demo', 'description' => 'Curated everyday clothing and accessories.', 'category_name' => 'Fashion', 'category_slug' => 'fashion', 'tin' => '123-456-789-001', 'payout_number' => '09171234503', 'line1' => '12 Lopez Avenue', 'barangay' => 'Batong Malake', 'city' => 'Los Baños', 'province' => 'Laguna', 'postal_code' => '4030'],
            ['owner_name' => 'Marco Villanueva', 'email' => 'seller.marco@lumear.test', 'phone' => '09171234504', 'date_of_birth' => '1989-06-18', 'shop_name' => 'Marco Home Finds', 'slug' => 'marco-home-finds-demo', 'description' => 'Useful home and living essentials for local households.', 'category_name' => 'Home & Living', 'category_slug' => 'home-living', 'tin' => '123-456-789-002', 'payout_number' => '09171234504', 'line1' => '9 Quezon Street', 'barangay' => 'Santo Angel Central', 'city' => 'Santa Cruz', 'province' => 'Laguna', 'postal_code' => '4009'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function catalog(): array
    {
        return [
            ['key' => 'nina-tote', 'seller_email' => 'seller.nina@lumear.test', 'store_category' => 'Bags', 'store_category_slug' => 'bags', 'position' => 1, 'name' => 'Luna Everyday Tote', 'slug' => 'demo-luna-everyday-tote', 'description' => 'A structured everyday tote with a roomy interior, secure zip closure, and comfortable shoulder straps.', 'sku' => 'DEMO-NINA-TOTE', 'variant_name' => 'Sand / Standard', 'options' => ['Color' => 'Sand'], 'price_minor' => 129900, 'stock' => 24, 'weight_grams' => 650, 'length_mm' => 380, 'width_mm' => 130, 'height_mm' => 290],
            ['key' => 'nina-pouch', 'seller_email' => 'seller.nina@lumear.test', 'store_category' => 'Accessories', 'store_category_slug' => 'accessories', 'position' => 2, 'name' => 'Canvas Travel Pouch', 'slug' => 'demo-canvas-travel-pouch', 'description' => 'A compact canvas pouch for daily essentials, chargers, cosmetics, and small travel accessories.', 'sku' => 'DEMO-NINA-POUCH', 'variant_name' => 'Natural / Standard', 'options' => ['Color' => 'Natural'], 'price_minor' => 49900, 'stock' => 36, 'weight_grams' => 180, 'length_mm' => 230, 'width_mm' => 70, 'height_mm' => 150],
            ['key' => 'marco-lamp', 'seller_email' => 'seller.marco@lumear.test', 'store_category' => 'Lighting', 'store_category_slug' => 'lighting', 'position' => 1, 'name' => 'Solace Table Lamp', 'slug' => 'demo-solace-table-lamp', 'description' => 'A warm bedside table lamp with a linen shade and compact wooden base for restful evening light.', 'sku' => 'DEMO-MARCO-LAMP', 'variant_name' => 'Walnut / Warm White', 'options' => ['Finish' => 'Walnut'], 'price_minor' => 189900, 'stock' => 15, 'weight_grams' => 1200, 'length_mm' => 220, 'width_mm' => 220, 'height_mm' => 420],
            ['key' => 'marco-basket', 'seller_email' => 'seller.marco@lumear.test', 'store_category' => 'Storage', 'store_category_slug' => 'storage', 'position' => 2, 'name' => 'Cedar Storage Basket', 'slug' => 'demo-cedar-storage-basket', 'description' => 'A woven storage basket designed for shelves, bedside essentials, and tidy everyday spaces.', 'sku' => 'DEMO-MARCO-BASKET', 'variant_name' => 'Medium / Natural', 'options' => ['Size' => 'Medium'], 'price_minor' => 79900, 'stock' => 20, 'weight_grams' => 900, 'length_mm' => 340, 'width_mm' => 260, 'height_mm' => 240],
        ];
    }
}
