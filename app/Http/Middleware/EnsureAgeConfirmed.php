<?php

namespace App\Http\Middleware;

use App\Services\DirectorySettings;
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
    private const CRAWLER_USER_AGENTS = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot',
        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'applebot', 'ia_archiver',
    ];

    public function __construct(private readonly DirectorySettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->boolean('site.age_gate_enabled')
            || $request->cookie(self::COOKIE) === '1'
            || $this->isKnownCrawler($request)
        ) {
            return $next($request);
        }

        return response()->view('age-gate.show', [
            'intendedUrl' => $request->fullUrl(),
        ]);
    }

    private function isKnownCrawler(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());
        if ($userAgent === '') {
            return false;
        }

        foreach (self::CRAWLER_USER_AGENTS as $needle) {
            if (str_contains($userAgent, $needle)) {
                return true;
            }
        }

        return false;
    }
}
