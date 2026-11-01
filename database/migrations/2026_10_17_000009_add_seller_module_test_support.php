<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Additive seller-module support. Existing marketplace data is untouched. */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->boolean('is_on_vacation')->default(false)->after('status');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('publication_status', 20)->default('active')->after('is_active');
            $table->unsignedInteger('length_mm')->nullable()->after('moderation_reason');
            $table->unsignedInteger('width_mm')->nullable()->after('length_mm');
            $table->unsignedInteger('height_mm')->nullable()->after('width_mm');
            $table->index(['seller_id', 'publication_status']);
        });

        Schema::create('seller_store_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('seller_store_categories')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('slug', 70);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['seller_id', 'name']);
            $table->unique(['seller_id', 'slug']);
            $table->index(['seller_id', 'parent_id', 'position']);
        });

        Schema::create('product_seller_store_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_store_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'seller_store_category_id'], 'product_store_category_unique');
        });

        Schema::create('seller_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->enum('discount_type', ['fixed', 'percentage']);
            $table->unsignedBigInteger('discount_minor')->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->unsignedBigInteger('discount_cap_minor')->nullable();
            $table->unsignedBigInteger('minimum_spend_minor')->default(0);
            $table->unsignedInteger('total_usage_limit');
            $table->unsignedInteger('per_buyer_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_claimable')->default(true);
            $table->enum('status', ['upcoming', 'ongoing', 'ended'])->default('upcoming');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['seller_id', 'status', 'starts_at', 'ends_at']);
        });

        Schema::create('seller_voucher_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['seller_voucher_id', 'product_id']);
        });

        Schema::create('buyer_seller_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_voucher_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('claimed_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['buyer_id', 'seller_voucher_id']);
        });

        Schema::create('product_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('discount_type', ['percentage', 'fixed_price']);
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->unsignedBigInteger('sale_price_minor')->nullable();
            $table->enum('status', ['upcoming', 'running', 'ended'])->default('upcoming');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['product_id', 'status', 'starts_at', 'ends_at']);
        });

        Schema::create('flash_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('flash_price_minor');
            $table->unsignedInteger('quantity_limit');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->enum('status', ['upcoming', 'running', 'ended'])->default('upcoming');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['seller_id', 'status', 'starts_at', 'ends_at']);
            $table->index(['product_id', 'starts_at', 'ends_at']);
        });

        Schema::create('bundle_deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['percentage_bundle', 'buy_x_get_y']);
            $table->unsignedInteger('buy_quantity');
            $table->unsignedInteger('get_quantity')->default(0);
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'is_active']);
        });

        Schema::create('bundle_deal_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['qualifier', 'reward'])->default('qualifier');
            $table->unique(['bundle_deal_id', 'product_id', 'role']);
        });

        Schema::create('return_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 100);
            $table->text('description');
            $table->enum('status', ['requested', 'approved', 'rejected', 'in_dispute', 'refunded'])->default('requested');
            $table->text('seller_response')->nullable();
            $table->timestamp('respond_by');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['seller_order_id', 'status']);
        });

        Schema::create('return_request_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_review_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_review_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('product_review_replies');
        Schema::dropIfExists('return_request_evidence');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('bundle_deal_products');
        Schema::dropIfExists('bundle_deals');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('product_sales');
        Schema::dropIfExists('buyer_seller_vouchers');
        Schema::dropIfExists('seller_voucher_products');
        Schema::dropIfExists('seller_vouchers');
        Schema::dropIfExists('product_seller_store_category');
        Schema::dropIfExists('seller_store_categories');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['seller_id', 'publication_status']);
            $table->dropColumn(['publication_status', 'length_mm', 'width_mm', 'height_mm']);
        });
        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropColumn('is_on_vacation');
        });
    }
};
