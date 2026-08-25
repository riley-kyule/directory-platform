<?php

namespace App\Http\Middleware;

use App\Services\ListingRotationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opportunistically kicks off listing rotation from real traffic instead of
 * a scheduled command. Must run before CachePublicPage in the route's
 * middleware list — CachePublicPage serves cache hits without invoking
 * anything after it, and a due rotation should still get dispatched even
 * when the page itself is served from cache.
 */
class TriggerListingRotation
{
    public function __construct(private readonly ListingRotationService $rotation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->rotation->triggerIfDue();

        return $next($request);
    }
}
