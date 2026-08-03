<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SearchTermLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SearchInsightsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('seo.search-insights');

        return view('seo.search-insights.index', [
            'terms' => SearchTermLog::query()
                ->orderByDesc('search_date')
                ->orderByDesc('search_count')
                ->paginate(50),
        ]);
    }
}
