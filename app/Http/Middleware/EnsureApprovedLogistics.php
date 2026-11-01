<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedLogistics
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isLogistics() && $request->user()->hasVerifiedEmail() && $request->user()->logisticsProvider?->status === 'approved', 403, 'A verified and approved logistics account is required.');

        return $next($request);
    }
}
