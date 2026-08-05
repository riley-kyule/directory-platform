<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\PageContent;
use App\Services\DirectorySettings;
use Illuminate\View\View;

class PublicAgencyController extends Controller
{
    public function __construct(private readonly DirectorySettings $settings) {}

    public function index(int $page = 1): View
    {
        $content = PageContent::query()->where('page_key', 'agencies')->firstOrFail();
        $perPage = 24;
        $query = Agency::query()->publiclyVisible()->withCount('publicProfiles')->orderBy('name');
        $totalPages = max(1, (int) ceil((clone $query)->count() / $perPage));
        abort_if($page < 1 || $page > $totalPages, 404);
        $agencies = $query->forPage($page, $perPage)->get();

        return view('directory.agencies.index', [
            'agencies' => $agencies,
            'content' => $content,
            'page' => $page,
            'totalPages' => $totalPages,
            'metaTitle' => $content->seo_title.($page > 1 ? ' — Page '.$page : ''),
            'metaDescription' => $content->meta_description,
            'canonicalUrl' => $page === 1 ? route('directory.agencies.index') : route('directory.agencies.page', $page),
            'robots' => 'index,follow',
            'newProfileDays' => $this->settings->integer('listings.new_profile_days'),
        ]);
    }

    public function show(string $agency, int $page = 1): View
    {
        $agency = Agency::query()
            ->publiclyVisible()
            ->where('slug', $agency)
            ->firstOrFail();
        $perPage = 12;
        $query = $agency->publicProfiles()
            ->with([
                'primaryLocation', 'sublocation', 'microLocation', 'owner', 'currentAgency.owner',
                'images' => fn ($query) => $query->where('status', 'approved')->limit(1),
                'contacts' => fn ($query) => $query->where('is_public', true),
                'currentPackageAssignment.package',
            ])
            ->orderBy('listing_rank')
            ->orderBy('profiles.id');
        $totalPages = max(1, (int) ceil((clone $query)->count() / $perPage));
        abort_if($page < 1 || $page > $totalPages, 404);
        $profiles = $query->forPage($page, $perPage)->get();

        return view('directory.agencies.show', [
            'agency' => $agency,
            'profiles' => $profiles,
            'page' => $page,
            'totalPages' => $totalPages,
            'metaTitle' => $agency->name.' — Active Profiles'.($page > 1 ? ' — Page '.$page : ''),
            'metaDescription' => str($agency->description ?: 'Browse active profiles represented by '.$agency->name.'.')->squish()->limit(155),
            'canonicalUrl' => $page === 1 ? route('directory.agencies.show', $agency->slug) : route('directory.agencies.show.page', [$agency->slug, $page]),
            'robots' => 'index,follow',
            'newProfileDays' => $this->settings->integer('listings.new_profile_days'),
        ]);
    }
}
