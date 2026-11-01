<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_start_a_product_conversation_and_send_an_image(): void
    {
        [$buyer, $seller, $product] = $this->marketplaceFixture();
        Storage::fake('public');

        $this->actingAs($buyer)
            ->post(route('buyer.messages.start', $seller), [
                'product_id' => $product->id,
                'body' => 'Hi, is this available today?',
            ])
            ->assertRedirect();

        $conversation = Conversation::firstOrFail();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'product_id' => $product->id,
            'body' => 'Hi, is this available today?',
        ]);

        $this->actingAs($buyer)
            ->post(route('messages.send', $conversation), [
                'body' => 'Here is the colour I mean.',
                'attachments' => [UploadedFile::fake()->image('review_photo.jpg')->size(1024)],
            ])
            ->assertRedirect();

        $attachmentPath = $conversation->fresh()->messages()->latest()->firstOrFail()->attachments()->firstOrFail()->path;
        Storage::disk('public')->assertExists($attachmentPath);

        $this->actingAs($buyer)->get(route('media.show', ['path' => $attachmentPath]))->assertOk();

        $otherBuyer = User::factory()->create(['email_verified_at' => now()]);
        $otherBuyer->roles()->attach(Role::where('name', 'buyer')->firstOrFail());

        $this->actingAs($otherBuyer)->get(route('media.show', ['path' => $attachmentPath]))->assertForbidden();
    }

    public function test_buyer_can_send_an_image_without_text_but_not_an_invalid_attachment(): void
    {
        [$buyer, $seller, $product] = $this->marketplaceFixture();
        Storage::fake('public');

        $this->actingAs($buyer)->post(route('buyer.messages.start', $seller), [
            'product_id' => $product->id,
            'body' => 'Can I see more photos?',
        ]);

        $conversation = Conversation::firstOrFail();

        $this->actingAs($buyer)
            ->post(route('messages.send', $conversation), [
                'attachments' => [UploadedFile::fake()->image('review_photo.png')->size(512)],
            ])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->from(route('messages.show', $conversation))
            ->post(route('messages.send', $conversation), [
                'body' => 'Valid text',
                'attachments' => [UploadedFile::fake()->create('installer.exe', 512, 'application/x-msdownload')],
            ])
            ->assertRedirect(route('messages.show', $conversation))
            ->assertSessionHasErrors('attachments.0');
    }

    public function test_buyer_message_length_and_conversation_access_are_enforced(): void
    {
        [$buyer, $seller, $product] = $this->marketplaceFixture();
        $otherBuyer = User::factory()->create(['email_verified_at' => now()]);
        $otherBuyer->roles()->attach(Role::where('name', 'buyer')->firstOrFail());

        $this->actingAs($buyer)->post(route('buyer.messages.start', $seller), [
            'product_id' => $product->id,
            'body' => 'Hello',
        ]);

        $conversation = Conversation::firstOrFail();

        $this->actingAs($buyer)
            ->from(route('messages.show', $conversation))
            ->post(route('messages.send', $conversation), ['body' => str_repeat('a', 1001)])
            ->assertRedirect(route('messages.show', $conversation))
            ->assertSessionHasErrors('body');

        $this->actingAs($otherBuyer)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($otherBuyer)
            ->post(route('messages.send', $conversation), ['body' => 'This must not be sent.'])
            ->assertForbidden();
    }

    /** @return array{0: User, 1: Seller, 2: Product} */
    private function marketplaceFixture(): array
    {
        $buyerRole = Role::create(['name' => 'buyer']);
        $sellerRole = Role::create(['name' => 'seller']);
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $buyer->roles()->attach($buyerRole);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->roles()->attach($sellerRole);
        $seller = Seller::create([
            'user_id' => $owner->id,
            'name' => 'Message Test Store',
            'slug' => 'message-test-store',
            'status' => 'approved',
            'commission_bps' => 500,
        ]);
        $category = Category::create(['name' => 'Message Test Category', 'slug' => 'message-test-category']);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Message Test Product',
            'slug' => 'message-test-product',
            'description' => 'A product used to test buyer messaging.',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MESSAGE-TEST-001',
            'name' => 'Standard',
            'price_minor' => 10000,
            'stock' => 4,
            'weight_grams' => 200,
            'is_active' => true,
        ]);

        return [$buyer, $seller, $product];
    }
}
