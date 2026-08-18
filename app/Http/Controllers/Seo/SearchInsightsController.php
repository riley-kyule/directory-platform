<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\ProfileConversionDaily;
use App\Models\SearchTermLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SearchInsightsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('seo.search-insights');

        $conversionQuery = ProfileConversionDaily::query()
            ->where('event_date', '>=', now()->subDays(29)->toDateString());

        return view('seo.search-insights.index', [
            'terms' => SearchTermLog::query()
                ->orderByDesc('search_date')
                ->orderByDesc('search_count')
                ->paginate(50),
            'totalContactClicks' => (clone $conversionQuery)->sum('contact_count'),
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
            'topProfiles' => (clone $conversionQuery)
                ->selectRaw('profile_id, SUM(contact_count) as total')
                ->with('profile:id,display_name,slug')
                ->groupBy('profile_id')
                ->orderByDesc('total')
                ->limit(20)
                ->get(),
        ]);
    }
}
