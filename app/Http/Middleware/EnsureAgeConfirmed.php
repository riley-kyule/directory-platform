<?php

namespace App\Http\Middleware;

use App\Services\DirectorySettings;
use App\Services\KnownCrawler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows an 18+ interstitial for first-time visitors when the admin-toggled
 * age gate is on. Must run BEFORE CachePublicPage in the middleware list on
 * every route it guards — CachePublicPage serves cache hits without invoking
 * anything downstream, so a gate placed after it would never re-run once a
 * page is cached, and every future visitor (confirmed or not) would get the
 * real page straight from cache.
 */
class EnsureAgeConfirmed
{
    public const COOKIE = 'age_verified';

    /**
     * Search-engine crawlers never carry the confirmation cookie, so without
     * this exemption every crawl would index the interstitial's "you must be
     * 18+" copy instead of the real page — this isn't cloaking (a human who
     * clicks through sees the identical page a bot would see), it's the same
     * carve-out virtually every real age-gated site relies on to stay
     * crawlable at all.
     */
    public function __construct(
        private readonly DirectorySettings $settings,
        private readonly KnownCrawler $crawler,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->boolean('site.age_gate_enabled')
            || $request->cookie(self::COOKIE) === '1'
            || $this->crawler->matches($request->userAgent())
        ) {
            return $next($request);
        }

        // Keep the canonical page and its metadata in the response. The public
        // layout renders an accessible blocking consent dialog for humans. This
        // avoids replacing canonical URLs with a thin 200/noindex interstitial.
        $request->attributes->set('age_gate_required', true);

        return $next($request);
    }
}
