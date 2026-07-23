<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\PageContent;
use App\Models\Profile;
use App\Services\PublicContactLinks;
use App\Services\PublicProfileListings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PublicDirectoryController extends Controller
{
    public function __construct(
        private readonly PublicProfileListings $listings,
        private readonly PublicContactLinks $contactLinks,
    ) {}

    public function home(): View
    {
        $content = PageContent::query()->where('page_key', 'homepage')->firstOrFail();

        return view('directory.index', [
            'heading' => $content->heading,
            'intro' => $content->intro_content,
            'bottomContent' => $content->bottom_content,
            'sectionContent' => $content->listing_sections,
            'sections' => $this->listings->sections(limit: 8),
            'location' => null,
            'page' => 1,
            'totalPages' => 1,
            'metaTitle' => $content->seo_title,
            'metaDescription' => $content->meta_description,
            'canonicalUrl' => route('directory.home'),
            'robots' => 'index,follow',
        ]);
    }

    public function city(string $city, int $page = 1): View
    {
        $location = Location::query()
            ->whereNull('parent_id')
            ->where('slug', $city)
            ->where('status', 'published')
            ->with('content')
            ->firstOrFail();

        return $this->locationPage($location, $page);
    }

    public function neighbourhood(string $city, string $neighbourhood, int $page = 1): View
    {
        $location = Location::query()
            ->where('slug', $neighbourhood)
            ->where('status', 'published')
            ->whereHas('parent', fn (Builder $query) => $query->where('slug', $city)->where('status', 'published'))
            ->with(['content', 'parent'])
            ->firstOrFail();

        return $this->locationPage($location, $page);
    }

    public function profile(string $profile): View
    {
        $profile = Profile::query()
            ->publiclyVisible()
            ->where('slug', $profile)
            ->with([
                'primaryLocation', 'sublocation', 'gender', 'ethnicity', 'build', 'bustSize',
                'owner', 'currentAgency.owner', 'services', 'languages', 'details.hairColor', 'details.hairLength',
                'details.sexualOrientation', 'rates.period', 'currentPackageAssignment.package',
                'contacts' => fn ($query) => $query->where('is_public', true)->orderBy('sort_order'),
                'images' => fn ($query) => $query->where('status', 'approved')->orderBy('sort_order'),
            ])
            ->firstOrFail();

        return view('directory.profile', [
            'profile' => $profile,
            'relatedProfiles' => $this->listings->relatedTo($profile),
            'contactLinks' => $this->contactLinks->for($profile),
            'metaTitle' => $profile->display_name.' — '.$profile->sublocation->name.', '.$profile->primaryLocation->name,
            'metaDescription' => str($profile->description)->squish()->limit(155),
            'canonicalUrl' => route('directory.profiles.show', $profile->slug),
            'robots' => 'index,follow',
        ]);
    }

    private function locationPage(Location $location, int $page): View
    {
        abort_if($page < 1, 404);
        $perPage = 12;
        $queries = [
            'vip' => $this->listings->forPackage('vip', $location),
            'premium' => $this->listings->forPackage('premium', $location),
            'basic' => $this->listings->forPackage('basic', $location),
            'new' => $this->listings->newProfiles($location),
        ];
        $counts = collect($queries)->map(fn (Builder $query) => (clone $query)->count());
        $totalPages = max(1, (int) ceil($counts->max() / $perPage));
        abort_if($page > $totalPages, 404);

        $sections = collect($queries)->map(fn (Builder $query) => $query->forPage($page, $perPage)->get())->all();
        $basePath = $location->content?->canonical_path
            ?? ($location->parent ? '/'.$location->parent->slug.'/'.$location->slug.'-escorts' : '/'.$location->slug.'-escorts');
        $canonicalPath = $page === 1 ? $basePath : $basePath.'/page/'.$page;
        $globalContent = PageContent::query()->where('page_key', 'homepage')->firstOrFail();

        return view('directory.index', [
            'heading' => $location->content?->heading ?? $location->name.' Escorts',
            'intro' => $location->content?->intro_content ?? 'Browse active provider profiles in '.$location->name.'.',
            'bottomContent' => $location->content?->bottom_content,
            'sectionContent' => $globalContent->listing_sections,
            'sections' => $sections,
            'location' => $location,
            'page' => $page,
            'totalPages' => $totalPages,
            'metaTitle' => $location->content?->seo_title ?? $location->name.' Escorts',
            'metaDescription' => $location->content?->meta_description ?? 'Browse active provider profiles in '.$location->name.'.',
            'canonicalUrl' => url($canonicalPath),
            'robots' => $location->is_indexable ? 'index,follow' : 'noindex,follow',
        ]);
    }
}
