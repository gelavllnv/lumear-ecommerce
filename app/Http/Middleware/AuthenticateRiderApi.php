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

        if (! $rider || ! $rider->is_active || $rider->management_status === 'Suspended') {
            return response()->json(['message' => 'This rider account is not active.'], 403);
        }

        DB::table('rider_api_tokens')->where('id', $record->id)->update([
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);

        $request->attributes->set('rider_api_user_id', (int) $record->user_id);
        $request->attributes->set('rider_api_rider', $rider);
        $request->attributes->set('rider_api_token_id', (int) $record->id);

        return $next($request);
    }
}
