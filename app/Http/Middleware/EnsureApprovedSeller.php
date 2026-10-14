<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSeller() && $request->user()->hasVerifiedEmail() && $request->user()->seller?->isApproved(), 403, 'A verified and approved seller account is required.');
        return $next($request);
    }
}
