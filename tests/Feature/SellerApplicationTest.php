<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EmailVerificationCode;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Notifications\SellerEmailVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_seller_application_creates_pending_seller_and_sends_a_code(): void
    {
        Storage::fake('local');
        Notification::fake();
        Role::create(['name' => 'seller']);
        $category = Category::create(['name' => 'Clothes', 'slug' => 'clothes']);

        $this->post(route('register.seller'), $this->application($category->id))
            ->assertRedirect(route('seller.email-verification.notice'));

        $user = User::where('email', 'seller@example.test')->firstOrFail();
        $seller = Seller::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('pending', $seller->status);
        $this->assertSame($category->id, $seller->category_id);
        $this->assertDatabaseHas('seller_profiles', ['seller_id' => $seller->id, 'business_type' => 'individual', 'tax_number' => '123-456-789-000']);
        $this->assertDatabaseHas('seller_kyc_submissions', ['seller_id' => $seller->id, 'status' => 'pending']);
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
        Notification::assertSentTo($user, SellerEmailVerificationCode::class);
    }

    public function test_business_documents_and_phone_tin_rules_are_enforced(): void
    {
        Role::create(['name' => 'seller']);
        $category = Category::create(['name' => 'Clothes', 'slug' => 'clothes']);
        $data = $this->application($category->id, ['seller_type' => 'corporation', 'phone' => '0917123456', 'tin' => 'invalid']);
        unset($data['sec_certificate'], $data['bir_certificate']);

        $this->from(route('register'))->post(route('register.seller'), $data)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['phone', 'tin', 'sec_certificate', 'bir_certificate']);
    }

    public function test_six_digit_code_verifies_seller_and_expired_or_old_codes_cannot_be_used(): void
    {
        $role = Role::create(['name' => 'seller']);
        $user = User::factory()->unverified()->create();
        $user->roles()->attach($role);
        EmailVerificationCode::create(['user_id' => $user->id, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(5)]);

        $this->actingAs($user)->post(route('seller.email-verification.verify'), ['code' => '123456'])
            ->assertRedirect(route('seller.status'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNotNull(EmailVerificationCode::firstOrFail()->used_at);
    }

    /** @return array<string, mixed> */
    private function application(int $categoryId, array $override = []): array
    {
        return array_replace([
            'first_name' => 'José',
            'last_name' => "Dela Cruz",
            'email' => 'seller@example.test',
            'phone' => '09171234567',
            'password' => 'Secure!123',
            'password_confirmation' => 'Secure!123',
            'shop_name' => 'Jose Clothes',
            'category_id' => $categoryId,
            'seller_type' => 'individual',
            'tin' => '123-456-789-000',
            'pickup_recipient' => 'Jose Dela Cruz',
            'pickup_phone' => '09171234567',
            'pickup_line1' => '1 Main Street',
            'pickup_region' => 'Region IV-A (CALABARZON)',
            'pickup_barangay' => 'Bayog',
            'pickup_city' => 'Los Baños',
            'pickup_province' => 'Laguna',
            'pickup_postal_code' => '4030',
            'government_id' => UploadedFile::fake()->image('government-id.png'),
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            'bir_certificate' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
            'seller_agreement' => '1',
        ], $override);
    }
}
