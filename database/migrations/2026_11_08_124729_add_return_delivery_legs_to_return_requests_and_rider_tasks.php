<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->string('delivery_status', 30)->default('not_started');
            $table->foreignId('return_current_provider_id')->nullable()
                ->constrained('logistics_providers')->nullOnDelete();
            $table->foreignId('return_destination_provider_id')->nullable()
                ->constrained('logistics_providers')->nullOnDelete();
        });

        Schema::table('rider_tasks', function (Blueprint $table): void {
            $table->string('return_leg', 30)->nullable()->after('return_request_id');
        });

        DB::table('rider_tasks')
            ->whereNotNull('return_request_id')
            ->update(['return_leg' => 'pickup']);

        DB::table('return_requests')
            ->where('pickup_status', 'received')
            ->update(['delivery_status' => 'awaiting_assignment']);

        $receivedReturns = DB::table('return_requests')
            ->where('pickup_status', 'received')
            ->get(['id']);

        foreach ($receivedReturns as $returnRequest) {
            $providerId = DB::table('rider_tasks')
                ->where('return_request_id', $returnRequest->id)
                ->orderByDesc('id')
                ->value('logistics_provider_id');

            if ($providerId) {
                DB::table('return_requests')
                    ->where('id', $returnRequest->id)
                    ->update(['return_current_provider_id' => $providerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('rider_tasks', function (Blueprint $table): void {
            $table->dropColumn('return_leg');
        });

        Schema::table('return_requests', function (Blueprint $table): void {
            $table->dropForeign(['return_current_provider_id']);
            $table->dropForeign(['return_destination_provider_id']);
            $table->dropColumn([
                'delivery_status',
                'return_current_provider_id',
                'return_destination_provider_id',
            ]);
        });
    }
};
