<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchProfilesRequest;
use App\Services\PublicProfileListings;
use App\Services\PublicSearchOptions;
use Illuminate\View\View;

class PublicSearchController extends Controller
{
    public function __construct(
        private readonly PublicProfileListings $listings,
        private readonly PublicSearchOptions $options,
    ) {}

    public function index(SearchProfilesRequest $request): View
    {
        $filters = collect($request->validated())->except('page')->all();
        $profiles = $this->listings->search($filters)
            ->paginate(24)
            ->withQueryString();

        return view('directory.search', $this->options->all() + [
            'profiles' => $profiles,
            'filters' => $filters,
            'metaTitle' => 'Search profiles — '.config('app.name'),
            'metaDescription' => 'Search active provider profiles by location, profile details, availability, and services.',
            'canonicalUrl' => route('directory.search'),
            'robots' => 'noindex,follow',
        ]);
    }
}
