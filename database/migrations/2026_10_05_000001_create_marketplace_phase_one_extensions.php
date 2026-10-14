<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration is intentionally additive. It never changes, removes, or
     * reseeds the existing marketplace tables or their data.
     */
    public function up(): void
    {
        Schema::create('auth_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_user_id');
            $table->string('provider_email')->nullable();
            $table->timestamp('linked_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });

        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('method', 30);
            $table->string('ip_address', 45)->nullable();
            $table->boolean('success');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['email', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        Schema::create('seller_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->string('tax_number')->nullable();
            $table->json('business_hours')->nullable();
            $table->text('shipping_policy')->nullable();
            $table->text('return_policy')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_onboarding_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('step', 50);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['seller_id', 'step']);
        });

        Schema::create('seller_kyc_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('government_id_path')->nullable();
            $table->string('business_permit_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('seller_id');
        });

        Schema::create('seller_payout_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('account_name');
            $table->text('account_number');
            $table->boolean('is_default')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'is_default']);
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity_delta');
            $table->unsignedInteger('stock_after');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['product_variant_id', 'created_at']);
            $table->index(['seller_order_id', 'type']);
        });

        Schema::create('order_timelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40);
            $table->string('event', 60);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->index(['seller_order_id', 'created_at']);
        });

        Schema::create('wallet_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('available_minor')->default(0);
            $table->unsignedBigInteger('pending_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_journals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('wallet_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type', 40);
            $table->integer('available_delta')->default(0);
            $table->integer('pending_delta')->default(0);
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['wallet_account_id', 'occurred_at']);
            $table->index(['seller_order_id', 'entry_type']);
        });

        Schema::create('seller_payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_payout_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('requested');
            $table->text('reason')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 80);
            $table->string('key', 120);
            $table->string('request_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->unique(['scope', 'key']);
            $table->index('expires_at');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('seller_payout_requests');
        Schema::dropIfExists('wallet_journals');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('order_timelines');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('seller_payout_accounts');
        Schema::dropIfExists('seller_kyc_submissions');
        Schema::dropIfExists('seller_onboarding_steps');
        Schema::dropIfExists('seller_profiles');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('auth_identities');
    }
};
