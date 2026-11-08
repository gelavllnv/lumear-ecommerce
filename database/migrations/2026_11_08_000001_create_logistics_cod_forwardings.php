<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_cod_forwardings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistics_provider_id')
                ->constrained('logistics_providers')
                ->restrictOnDelete();

            $table->foreignId('submitted_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('reference', 40)->unique();
            $table->uuid('request_key');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('PHP');

            $table->string('status', 20)
                ->default('pending_admin');

            $table->string('admin_reference', 120)
                ->nullable()
                ->unique();

            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['logistics_provider_id', 'request_key'],
                'cod_forward_request_unique'
            );

            $table->index(
                ['logistics_provider_id', 'status'],
                'cod_forward_provider_status'
            );
        });

        Schema::create('logistics_cod_forwarding_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('forwarding_id')
                ->constrained('logistics_cod_forwardings')
                ->restrictOnDelete();

            $table->foreignId('cod_wallet_entry_id')
                ->constrained('logistics_cod_wallet_entries')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->unique(
                ['forwarding_id', 'cod_wallet_entry_id'],
                'cod_forward_item_unique'
            );
        });

        Schema::create('logistics_cod_forwarding_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('forwarding_id')
                ->constrained('logistics_cod_forwardings')
                ->restrictOnDelete();

            $table->string('action', 30);
            $table->string('actor', 30);
            $table->text('description');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_cod_forwarding_events');
        Schema::dropIfExists('logistics_cod_forwarding_items');
        Schema::dropIfExists('logistics_cod_forwardings');
    }
};
