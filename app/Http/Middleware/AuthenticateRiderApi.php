<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRiderApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Rider authentication is required.'], 401);
        }

        $record = DB::table('rider_api_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Your rider session has expired. Please sign in again.'], 401);
        }

        $rider = DB::table('riders')
            ->where('user_id', $record->user_id)
            ->first();

        if (! $rider
            || DB::table('users')->where('id', $record->user_id)->value('is_suspended')
            || ! DB::table('logistics_providers')->where('id', $rider->logistics_provider_id)->where('status', 'approved')->exists()) {
            return response()->json(['message' => 'This rider account is not active.'], 403);
        }

        $request->attributes->set('rider_api_user_id', (int) $record->user_id);
        $request->attributes->set('rider_api_rider', $rider);
        $request->attributes->set('rider_api_token_id', (int) $record->id);

        if (in_array($rider->management_status, ['Suspended', 'Terminated'], true)) {
            $isNotificationRequest = $request->is('api/rider/notifications')
                || $request->is('rider/notifications');
            $isLogoutRequest = $request->is('api/rider/auth/logout')
                || $request->is('rider/auth/logout');

            if (! $isNotificationRequest && ! $isLogoutRequest) {
                $isTerminated = $rider->management_status === 'Terminated';

                return response()->json([
                    'message' => $isTerminated
                        ? 'Your rider account has been terminated. You cannot access rider services or receive assignments.'
                        : 'Your rider account is suspended. You cannot go online or receive assignments.',
                    'suspended' => ! $isTerminated,
                    'terminated' => $isTerminated,
                    'account_status' => $rider->management_status,
                ], 403);
            }
        }

        DB::table('rider_api_tokens')->where('id', $record->id)->update([
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);

        return $next($request);
    }
}
