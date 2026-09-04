<?php

namespace App\Http\Middleware;

use App\Services\PublicPageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves guest GET requests from PublicPageCache instead of re-rendering.
 *
 * Only applied to routes that render identically for every anonymous
 * visitor: no @auth-conditional content beyond the shared layout nav (which
 * this middleware sidesteps by never caching for a logged-in request) and no
 * CSRF-protected forms in the page body.
 */
class CachePublicPage
{
    public function __construct(private readonly PublicPageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Public directory routes do not use query parameters. Caching every
        // arbitrary variation (for example ?anything=random) would let one
        // visitor create an unbounded number of otherwise identical entries.
        // Real campaign parameters may still render normally; they simply do
        // not consume shared cache storage.
        if (! $request->isMethod('GET') || $request->user() || $request->query->count() > 0) {
            return $next($request);
        }

        // The age-gated render is identical for every unconfirmed guest, so it
        // caches under its own variant rather than forcing an uncached render
        // on every first-time visitor.
        $variant = $request->attributes->get('age_gate_required') ? 'age-gate' : '';

        $rendered = false;
        $cached = $this->cache->remember($request->fullUrl(), function () use ($request, $next, &$rendered): array {
            $rendered = true;
            $response = $next($request);

            return [
                'status' => $response->getStatusCode(),
                'content' => $response->getContent(),
                'content_type' => $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
                'location' => $response->headers->get('Location'),
            ];
        }, $variant);

        $response = response($cached['content'], $cached['status'])
            ->header('Content-Type', $cached['content_type'])
            ->header('X-Page-Cache', $rendered ? 'miss' : 'hit');

        if ($cached['location']) {
            $response->headers->set('Location', $cached['location']);
        }

        return $response;
    }
}
