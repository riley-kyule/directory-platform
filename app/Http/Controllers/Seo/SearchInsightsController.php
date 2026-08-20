<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\ProfileConversionDaily;
use App\Models\ProfileViewDaily;
use App\Models\SearchTermLog;
use App\Services\DirectorySettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SearchInsightsController extends Controller
{
    public function index(DirectorySettings $settings): View
    {
        Gate::authorize('seo.search-insights');

        $conversionQuery = ProfileConversionDaily::query()
            ->where('event_date', '>=', now()->subDays(29)->toDateString());
        $viewQuery = ProfileViewDaily::query()
            ->where('event_date', '>=', now()->subDays(29)->toDateString());
        $profileViews = (clone $viewQuery)
            ->selectRaw('profile_id, SUM(view_count) as total')
            ->groupBy('profile_id')
            ->pluck('total', 'profile_id');
        $profileClicks = (clone $conversionQuery)
            ->selectRaw('profile_id, SUM(contact_count) as total')
            ->groupBy('profile_id')
            ->pluck('total', 'profile_id');
        $profileIds = $profileViews->keys()->merge($profileClicks->keys())->unique()->values();
        $profiles = Profile::query()
            ->whereKey($profileIds)
            ->get(['id', 'display_name', 'slug'])
            ->keyBy('id');
        $totalViews = (int) (clone $viewQuery)->sum('view_count');
        $totalContactClicks = (int) (clone $conversionQuery)->sum('contact_count');

        return view('seo.search-insights.index', [
            'terms' => SearchTermLog::query()
                ->orderByDesc('search_date')
                ->orderByDesc('search_count')
                ->paginate(50),
            'totalProfileViews' => $totalViews,
            'totalContactClicks' => $totalContactClicks,
            'overallClickThroughRate' => $totalViews > 0 ? round(($totalContactClicks / $totalViews) * 100, 1) : null,
            'profilePerformance' => $profileIds->map(function (int|string $profileId) use ($profileViews, $profileClicks, $profiles): array {
                $views = (int) $profileViews->get($profileId, 0);
                $clicks = (int) $profileClicks->get($profileId, 0);

                return [
                    'profile' => $profiles->get($profileId),
                    'views' => $views,
                    'clicks' => $clicks,
                    'ctr' => $views > 0 ? round(($clicks / $views) * 100, 1) : null,
                ];
            })->sort(fn (array $left, array $right): int => [$right['views'], $right['clicks']] <=> [$left['views'], $left['clicks']])
                ->take(20)
                ->values(),
            'verification' => [
                'google' => $settings->string('seo.google_site_verification') !== '',
                'bing' => $settings->string('seo.bing_site_verification') !== '',
            ],
            'sitemapUrl' => route('sitemaps.index'),
            'channelTotals' => (clone $conversionQuery)
                ->selectRaw('channel, SUM(contact_count) as total')
                ->groupBy('channel')
                ->orderByDesc('total')
                ->pluck('total', 'channel'),
            'placementTotals' => (clone $conversionQuery)
                ->selectRaw('placement, SUM(contact_count) as total')
                ->groupBy('placement')
                ->orderByDesc('total')
                ->pluck('total', 'placement'),
        ]);
    }
}
