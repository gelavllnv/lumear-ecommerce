<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsNotificationController extends Controller
{
    private function providerId(): int
    {
        $id = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless($id, 403, 'Approved Logistics account required.');

        return (int) $id;
    }

    public function index(): JsonResponse
    {
        $providerId = $this->providerId();

        $query = DB::table('logistics_notifications')
            ->where('logistics_provider_id', $providerId);

        $unread = (clone $query)
            ->whereNull('read_at')
            ->count();

        $notifications = (clone $query)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get([
                'id',
                'type',
                'title',
                'message',
                'url',
                'read_at',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'unread_count' => $unread,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $providerId = $this->providerId();

        $exists = DB::table('logistics_notifications')
            ->where('id', $id)
            ->where('logistics_provider_id', $providerId)
            ->exists();

        abort_unless($exists, 404, 'Notification not found.');

        DB::table('logistics_notifications')
            ->where('id', $id)
            ->where('logistics_provider_id', $providerId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        DB::table('logistics_notifications')
            ->where('logistics_provider_id', $this->providerId())
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
        ]);
    }
}
