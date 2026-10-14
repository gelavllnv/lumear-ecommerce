<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isBuyer(), 403, 'A buyer account is required to shop and checkout.');

        return $next($request);
    }
}
