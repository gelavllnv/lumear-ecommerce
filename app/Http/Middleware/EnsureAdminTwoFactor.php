<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $verifiedAt = $request->session()->get('admin_2fa_verified_at');
        if (! $verifiedAt || now()->diffInHours($verifiedAt) >= 8) {
            return redirect()->route('admin.2fa.challenge');
        }

        return $next($request);
    }
}
