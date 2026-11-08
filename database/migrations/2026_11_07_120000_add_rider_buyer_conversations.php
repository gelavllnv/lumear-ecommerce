<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('conversations', ['buyer_id'], 'index')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->index('buyer_id', 'conversations_buyer_id_index');
            });
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique(['buyer_id', 'seller_id']);
            $table->dropForeign(['seller_id']);
            $table->foreignId('seller_id')->nullable()->change();
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->after('seller_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->after('rider_id')->constrained('shipments')->cascadeOnDelete();
            $table->unique(['buyer_id', 'rider_id', 'shipment_id'], 'conversations_buyer_rider_shipment_unique');
            $table->index(['rider_id', 'last_message_at'], 'conversations_rider_last_message_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conversations_buyer_rider_shipment_unique');
            $table->dropIndex('conversations_rider_last_message_index');
            $table->dropConstrainedForeignId('shipment_id');
            $table->dropConstrainedForeignId('rider_id');
            $table->dropForeign(['seller_id']);
            $table->foreignId('seller_id')->nullable(false)->change();
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->unique(['buyer_id', 'seller_id']);
        });

        if (Schema::hasIndex('conversations', 'conversations_buyer_id_index')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dropIndex('conversations_buyer_id_index');
            });
        }
    }
};
