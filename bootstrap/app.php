<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lumear is served behind Cloudflare and Nginx in production. Trusting the
        // proxy headers lets Laravel generate HTTPS URLs (including signed email
        // verification and Google OAuth callbacks) instead of localhost / HTTP URLs.
        // Keep the origin firewall restricted to Cloudflare so these headers cannot
        // be forged by a direct public request.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'seller.approved' => \App\Http\Middleware\EnsureApprovedSeller::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'buyer' => \App\Http\Middleware\EnsureBuyer::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'admin.2fa' => \App\Http\Middleware\EnsureAdminTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
