<?php

use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\EnsureAgeConfirmed;
use App\Http\Middleware\EnsurePrivilegedMfa;
use App\Http\Middleware\ResolveDirectoryRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            TrackUserActivity::class,
            EnsurePrivilegedMfa::class,
            ResolveDirectoryRedirects::class,
        ]);
        $middleware->alias([
            'cache.public' => CachePublicPage::class,
            'age.gate' => EnsureAgeConfirmed::class,
        ]);
        // Behind Cloudflare (or any TLS-terminating proxy) the app only ever
        // sees the proxy's own IP, so it must trust forwarded headers to know
        // the visitor's real IP/scheme. '*' trusts whichever host actually
        // connects to PHP-FPM — safe here because shared cPanel hosting
        // doesn't offer a way to firewall origin traffic to Cloudflare's IP
        // ranges from inside the app; do that at the host/DNS level too via
        // Cloudflare's "Authenticated Origin Pulls" if your plan supports it.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
