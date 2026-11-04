<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LogisticsNotification
{
    public static function create(
        int $providerId,
        string $type,
        string $title,
        string $message,
        ?string $url = null
    ): void {
        DB::table('logistics_notifications')->insert([
            'logistics_provider_id' => $providerId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
