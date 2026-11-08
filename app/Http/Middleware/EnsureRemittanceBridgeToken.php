<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRemittanceBridgeToken
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $expected = (string) config(
            'lumear-remittance.bridge_token',
            ''
        );

        if (strlen($expected) < 32) {
            return response()->json([
                'message' => 'Admin remittance connection is not configured.',
            ], 503);
        }

        $provided = $request->bearerToken();

        if (
            ! $provided
            || ! hash_equals(
                hash('sha256', $expected),
                hash('sha256', $provided)
            )
        ) {
            return response()->json([
                'message' => 'Unauthorized admin remittance connection.',
            ], 401);
        }

        return $next($request);
    }
}
