<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalHost = (string) config('security.canonical_host');
        if (! app()->environment('production') || $canonicalHost === '' || $request->getHost() === $canonicalHost) {
            return $next($request);
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            abort(400, 'Invalid request host.');
        }

        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return redirect($scheme.'://'.$canonicalHost.$request->getRequestUri(), 301);
    }
}
