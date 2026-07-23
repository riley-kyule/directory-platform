<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\PageContent;
use App\Services\DirectorySettings;
use Illuminate\View\View;

class PublicAgencyController extends Controller
{
    public function __construct(private readonly DirectorySettings $settings) {}

    public function index(): View
    {
        $content = PageContent::query()->where('page_key', 'agencies')->firstOrFail();
        $agencies = Agency::query()
            ->publiclyVisible()
            ->withCount('publicProfiles')
            ->orderBy('name')
            ->paginate(24);
        $page = max(1, $agencies->currentPage());

        return view('directory.agencies.index', [
            'agencies' => $agencies,
            'content' => $content,
            'metaTitle' => $content->seo_title.($page > 1 ? ' — Page '.$page : ''),
            'metaDescription' => $content->meta_description,
            'canonicalUrl' => route('directory.agencies.index').($page > 1 ? '?page='.$page : ''),
            'robots' => 'index,follow',
            'newProfileDays' => $this->settings->integer('listings.new_profile_days'),
        ]);
    }

    public function show(string $agency): View
    {
        $agency = Agency::query()
            ->publiclyVisible()
            ->where('slug', $agency)
            ->firstOrFail();
        $profiles = $agency->publicProfiles()
            ->with([
                'primaryLocation', 'sublocation', 'owner', 'currentAgency.owner',
                'images' => fn ($query) => $query->where('status', 'approved')->limit(1),
                'contacts' => fn ($query) => $query->where('is_public', true),
                'currentPackageAssignment.package',
            ])
            ->orderBy('listing_rank')
            ->orderBy('profiles.id')
            ->paginate(12);
        $page = max(1, $profiles->currentPage());

        return view('directory.agencies.show', [
            'agency' => $agency,
            'profiles' => $profiles,
            'metaTitle' => $agency->name.' — Active Profiles'.($page > 1 ? ' — Page '.$page : ''),
            'metaDescription' => str($agency->description ?: 'Browse active profiles represented by '.$agency->name.'.')->squish()->limit(155),
            'canonicalUrl' => route('directory.agencies.show', $agency->slug).($page > 1 ? '?page='.$page : ''),
            'robots' => 'index,follow',
            'newProfileDays' => $this->settings->integer('listings.new_profile_days'),
        ]);
    }
}
