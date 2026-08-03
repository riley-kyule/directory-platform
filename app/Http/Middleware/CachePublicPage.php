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
        if (! $request->isMethod('GET') || $request->user()) {
            return $next($request);
        }

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
        });

        $response = response($cached['content'], $cached['status'])
            ->header('Content-Type', $cached['content_type'])
            ->header('X-Page-Cache', $rendered ? 'miss' : 'hit');

        if ($cached['location']) {
            $response->headers->set('Location', $cached['location']);
        }

        return $response;
    }
}
