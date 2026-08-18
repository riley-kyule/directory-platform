<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\PageContent;
use App\Models\Profile;
use App\Services\DirectorySettings;
use App\Services\LocationSidebarTree;
use App\Services\PublicContactLinks;
use App\Services\PublicProfileListings;
use App\Services\PublicSearchOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicDirectoryController extends Controller
{
    public function __construct(
        private readonly PublicProfileListings $listings,
        private readonly PublicContactLinks $contactLinks,
        private readonly DirectorySettings $settings,
        private readonly PublicSearchOptions $searchOptions,
    ) {}

    public function home(): View
    {
        $content = PageContent::query()->where('page_key', 'homepage')->firstOrFail();

        return view('directory.index', $this->searchOptions->all() + [
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
            'newProfileDays' => $this->settings->integer('listings.new_profile_days'),
            'nearbyLocations' => collect(),
            'lastReviewedAt' => null,
            'faqPairs' => [],
        ]);
    }

    public function allLocations(): View
    {
        return view('directory.locations', [
            'locationTree' => app(LocationSidebarTree::class)->buildIndexable(),
            'metaTitle' => 'All locations | '.config('app.name'),
            'metaDescription' => 'Every city, neighbourhood, and area covered by '.config('app.name').'.',
            'canonicalUrl' => route('directory.locations.index'),
            'robots' => 'index,follow',
        ]);
    }

    public function city(string $city, int $page = 1): View|RedirectResponse
    {
        $location = $this->findBySlugOrAlias(
            Location::query()->whereNull('parent_id')->where('status', 'published')->with('content'),
            $city,
        );
        abort_unless($location, 404);

        if ($location->slug !== $city) {
            return $this->redirectToCanonical($location, $page);
        }

        return $this->locationPage($location, $page);
    }

    public function neighbourhood(string $city, string $neighbourhood, int $page = 1): View|RedirectResponse
    {
        $cityLocation = $this->findBySlugOrAlias(
            Location::query()->whereNull('parent_id')->where('status', 'published'),
            $city,
        );
        abort_unless($cityLocation, 404);

        $location = $this->findBySlugOrAlias(
            Location::query()->where('parent_id', $cityLocation->id)->where('status', 'published')->with(['content', 'parent']),
            $neighbourhood,
        );
        abort_unless($location, 404);

        if ($cityLocation->slug !== $city || $location->slug !== $neighbourhood) {
            return $this->redirectToCanonical($location, $page);
        }

        return $this->locationPage($location, $page);
    }

    public function microLocation(string $city, string $neighbourhood, string $micro, int $page = 1): View|RedirectResponse
    {
        $cityLocation = $this->findBySlugOrAlias(
            Location::query()->whereNull('parent_id')->where('status', 'published'),
            $city,
        );
        abort_unless($cityLocation, 404);

        $neighbourhoodLocation = $this->findBySlugOrAlias(
            Location::query()->where('parent_id', $cityLocation->id)->where('status', 'published'),
            $neighbourhood,
        );
        abort_unless($neighbourhoodLocation, 404);

        $location = $this->findBySlugOrAlias(
            Location::query()
                ->whereIn('type', ['area', 'landmark'])
                ->where('parent_id', $neighbourhoodLocation->id)
                ->where('status', 'published')
                ->with(['content', 'parent.parent']),
            $micro,
        );
        abort_unless($location, 404);

        if ($cityLocation->slug !== $city || $neighbourhoodLocation->slug !== $neighbourhood || $location->slug !== $micro) {
            return $this->redirectToCanonical($location, $page);
        }

        return $this->locationPage($location, $page);
    }

    public function profile(string $profile): View
    {
        $profile = Profile::query()
            ->publiclyVisible()
            ->where('slug', $profile)
            ->with([
                'primaryLocation', 'sublocation', 'microLocation', 'gender', 'ethnicity', 'build', 'bustSize',
                'owner', 'currentAgency.owner', 'services', 'languages', 'details.hairColor', 'details.hairLength',
                'details.sexualOrientation', 'rates.period', 'currentPackageAssignment.package',
                'contacts' => fn ($query) => $query->where('is_public', true)->orderBy('sort_order'),
                'images' => fn ($query) => $query->where('status', 'approved')->orderBy('sort_order'),
            ])
            ->firstOrFail();

        $reviews = $profile->reviews()->published()->latest()->get();
        $canonicalUrl = route('directory.profiles.show', $profile->slug);
        $metaDescription = str($profile->description)->squish()->limit(155)->toString();
        $socialImage = $profile->images->first()?->publicUrl('profile');
        if ($socialImage && ! Str::startsWith($socialImage, ['http://', 'https://'])) {
            $socialImage = url($socialImage);
        }

        return view('directory.profile', [
            'profile' => $profile,
            'relatedProfiles' => $this->listings->relatedTo($profile),
            'contactLinks' => $this->contactLinks->for($profile),
            'metaTitle' => $profile->display_name.' — '.($profile->microLocation?->name ?? $profile->sublocation->name).', '.$profile->primaryLocation->name,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'socialImage' => $socialImage,
            'robots' => 'index,follow',
            'newProfileDays' => $this->settings->integer('listings.new_profile_days'),
            'reviews' => $reviews,
            'reviewStats' => [
                'average' => $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null,
                'count' => $reviews->count(),
            ],
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
        $basePath = $location->publicPath();
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
            'metaTitle' => ($location->content?->seo_title ?? $location->name.' Escorts').($page > 1 ? ' — Page '.$page : ''),
            'metaDescription' => ($location->content?->meta_description ?? 'Browse active provider profiles in '.$location->name.'.').($page > 1 ? ' Page '.$page.' of '.$totalPages.'.' : ''),
            'canonicalUrl' => url($canonicalPath),
            'previousUrl' => $page > 1 ? url($page === 2 ? $basePath : $basePath.'/page/'.($page - 1)) : null,
            'nextUrl' => $page < $totalPages ? url($basePath.'/page/'.($page + 1)) : null,
            'robots' => $location->is_indexable ? 'index,follow' : 'noindex,follow',
            'newProfileDays' => $this->settings->integer('listings.new_profile_days'),
            'nearbyLocations' => $counts->sum() === 0 ? $this->nearbyLocations($location) : collect(),
            'lastReviewedAt' => $location->content?->last_reviewed_at,
            'faqPairs' => $location->content?->faqPairs() ?? [],
        ]);
    }

    /**
     * Resolve a URL segment against a location's canonical slug first, then its
     * aliases, so alternate spellings redirect to the canonical page instead of
     * 404ing or becoming a competing indexable URL.
     */
    private function findBySlugOrAlias(Builder $query, string $slug): ?Location
    {
        return (clone $query)->where('slug', $slug)->first()
            ?? (clone $query)->whereHas('aliases', fn (Builder $aliasQuery) => $aliasQuery->where('normalized_alias', Str::slug($slug)))->first();
    }

    private function redirectToCanonical(Location $location, int $page): RedirectResponse
    {
        $location->loadMissing('content');
        $path = $location->publicPath();
        if ($page > 1) {
            $path .= '/page/'.$page;
        }

        return redirect($path, 301);
    }

    /**
     * Suggest the parent location and sibling locations that do have active
     * inventory, for when a location page has none of its own to show.
     *
     * @return Collection<int, Location>
     */
    private function nearbyLocations(Location $location): Collection
    {
        $parent = $location->parent_id
            ? Location::query()->where('id', $location->parent_id)->where('status', 'published')->where('active_profile_count', '>', 0)->with('content')->first()
            : null;

        $siblings = Location::query()
            ->where('parent_id', $location->parent_id)
            ->where('status', 'published')
            ->whereKeyNot($location->id)
            ->where('active_profile_count', '>', 0)
            ->orderByDesc('active_profile_count')
            ->with('content')
            ->limit(4)
            ->get();

        return collect([$parent])->filter()->concat($siblings)->unique('id')->take(5);
    }
}
