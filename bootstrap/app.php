<?php

use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\EnforceCanonicalHost;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAgeConfirmed;
use App\Http\Middleware\EnsurePrivilegedMfa;
use App\Http\Middleware\ResolveDirectoryRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackUserActivity;
use App\Http\Middleware\TriggerListingRotation;
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
        $middleware->web(append: [
            SecurityHeaders::class,
            EnforceCanonicalHost::class,
            EnsureActiveAccount::class,
            TrackUserActivity::class,
            EnsurePrivilegedMfa::class,
            ResolveDirectoryRedirects::class,
        ]);
        $middleware->alias([
            'cache.public' => CachePublicPage::class,
            'age.gate' => EnsureAgeConfirmed::class,
            'listing.rotation' => TriggerListingRotation::class,
        ]);
        // These endpoints accept only aggregate, non-identifying counters and
        // have strict validation/throttles. Exempting them allows cached public
        // pages to use sendBeacon without embedding a guest-specific CSRF token
        // in the shared page cache.
        $middleware->validateCsrfTokens(except: ['conversion/contact', 'conversion/profile-view']);
        // Trust forwarded headers only from explicitly configured proxy IPs or
        // CIDRs. Trusting every sender allows direct-origin clients to spoof the
        // IP used by throttles and audit logs.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies, headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*'),
        );
    })->create();
