<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['buyer_id', 'product_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        foreach (['seller_vouchers', 'flash_sales', 'product_sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('is_paused')->default(false)->after('status');
                $table->timestamp('archived_at')->nullable()->after('ends_at');
            });
        }

        Schema::table('bundle_deals', fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->after('ends_at'));
    }

    public function down(): void
    {
        Schema::table('bundle_deals', fn (Blueprint $table) => $table->dropColumn('archived_at'));
        foreach (['seller_vouchers', 'flash_sales', 'product_sales'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['is_paused', 'archived_at']));
        }
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('saved_products');
    }
};
