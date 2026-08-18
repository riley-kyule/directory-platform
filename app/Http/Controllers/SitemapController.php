<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Location;
use App\Models\LocationContent;
use App\Models\PageContent;
use App\Models\Profile;
use App\Services\PolicyAcceptanceService;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $maps = collect([
            ['url' => route('sitemaps.editorial'), 'lastmod' => $this->editorialLastmod()],
        ])
            ->merge($this->chunkMaps('locations', $this->locationsQuery()->count(), fn (int $page) => $this->locationsChunkLastmod($page)))
            ->merge($this->chunkMaps('profiles', Profile::query()->publiclyVisible()->count(), fn (int $page) => $this->pageMaxLastmod(Profile::query()->publiclyVisible(), $page, 'content_updated_at')))
            ->merge($this->chunkMaps('agencies', Agency::query()->publiclyVisible()->count(), fn (int $page) => $this->pageMaxLastmod(Agency::query()->publiclyVisible(), $page, 'updated_at')));

        return $this->xml('sitemaps.index', ['maps' => $maps]);
    }

    public function editorial(): Response
    {
        $pageContent = PageContent::query()->whereIn('page_key', ['homepage', 'agencies'])->get()->keyBy('page_key');
        $locationsLastmod = $this->latestTimestamp([
            Location::query()->where('status', 'published')->max('updated_at'),
            LocationContent::query()->max('updated_at'),
        ]);
        $agenciesLastmod = $this->latestTimestamp([
            $pageContent->get('agencies')?->updated_at,
            Agency::query()->where('status', 'active')->max('updated_at'),
        ]);

        $entries = collect([
            ['url' => route('directory.home'), 'lastmod' => $pageContent->get('homepage')?->updated_at ?? $this->editorialLastmod(), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['url' => route('directory.locations.index'), 'lastmod' => $locationsLastmod, 'changefreq' => 'daily', 'priority' => '0.8'],
            ['url' => route('directory.agencies.index'), 'lastmod' => $agenciesLastmod, 'changefreq' => 'daily', 'priority' => '0.7'],
        ]);

        $policies = app(PolicyAcceptanceService::class)->latestPublished();

        return $this->urlSet($entries->concat($policies->map(fn ($policy) => [
            'url' => $policy->publicRoute(),
            'lastmod' => $policy->updated_at,
            'changefreq' => 'monthly',
            'priority' => '0.3',
        ])));
    }

    public function locations(int $page): Response
    {
        $locations = $this->page($this->locationsQuery()->with(['content', 'parent']), $page);

        return $this->urlSet($locations->map(fn (Location $location) => [
            'url' => url($location->content->canonical_path),
            'lastmod' => $location->content->updated_at,
            'changefreq' => 'daily',
            'priority' => $location->parent_id === null ? '0.8' : ($location->isMicroLocation() ? '0.5' : '0.6'),
        ]));
    }

    public function profiles(int $page): Response
    {
        $profiles = $this->page(
            Profile::query()->publiclyVisible()->with(['images' => fn ($query) => $query->where('status', 'approved')->orderBy('sort_order')]),
            $page,
        );

        return $this->urlSet($profiles->map(fn (Profile $profile) => [
            'url' => route('directory.profiles.show', $profile->slug),
            'lastmod' => $profile->content_updated_at ?? $profile->updated_at,
            'changefreq' => 'daily',
            'priority' => '0.6',
            'images' => $profile->images->map(fn ($image) => $image->publicUrl('card'))->filter()->values()->all(),
        ]));
    }

    public function agencies(int $page): Response
    {
        $agencies = $this->page(Agency::query()->publiclyVisible(), $page);

        return $this->urlSet($agencies->map(fn (Agency $agency) => [
            'url' => route('directory.agencies.show', $agency->slug),
            'lastmod' => $agency->updated_at,
            'changefreq' => 'weekly',
            'priority' => '0.5',
        ]));
    }

    public function robots(): Response
    {
        $disallow = collect([
            '/search', '/dashboard', '/profile', '/profiles', '/my-profiles', '/onboarding', '/admin', '/staff', '/seo',
        ])->map(fn (string $path) => 'Disallow: '.$path)->implode("\n");

        return response(
            "User-agent: *\n{$disallow}\n\nSitemap: ".route('sitemaps.index')."\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    private function locationsQuery(): Builder
    {
        return Location::query()
            ->where('status', 'published')
            ->where('is_indexable', true)
            ->whereHas('content', fn (Builder $query) => $query->where('content_status', 'approved'));
    }

    /** @return Collection<int, array{url: string, lastmod: mixed}> */
    private function chunkMaps(string $type, int $count, Closure $lastmodForPage): Collection
    {
        if ($count === 0) {
            return collect();
        }

        $pages = (int) ceil($count / $this->chunkSize());

        return collect(range(1, $pages))->map(fn (int $page) => [
            'url' => route('sitemaps.'.$type, $page),
            'lastmod' => $lastmodForPage($page) ?? now(),
        ]);
    }

    /**
     * MAX(updated_at) can't just be combined with forPage()'s LIMIT/OFFSET —
     * without a GROUP BY, the aggregate collapses to one row before OFFSET
     * is applied, so any page beyond the first would silently return no
     * rows. Pulling the column and reducing in PHP sidesteps that.
     */
    private function pageMaxLastmod(Builder $query, int $page, string $column): mixed
    {
        return $query->orderBy('id')->forPage($page, $this->chunkSize())->pluck($column)->max();
    }

    private function locationsChunkLastmod(int $page): mixed
    {
        $ids = $this->locationsQuery()->orderBy('id')->forPage($page, $this->chunkSize())->pluck('id');

        return LocationContent::query()->whereIn('location_id', $ids)->pluck('updated_at')->max();
    }

    /** @return Collection<int, mixed> */
    private function page(Builder $query, int $page): Collection
    {
        abort_if($page < 1, 404);
        $count = (clone $query)->count();
        abort_if($count === 0 || $page > (int) ceil($count / $this->chunkSize()), 404);

        return $query->orderBy('id')->forPage($page, $this->chunkSize())->get();
    }

    private function chunkSize(): int
    {
        return max(1, min(50_000, (int) config('directory.sitemap_chunk_size')));
    }

    private function editorialLastmod(): Carbon
    {
        return $this->latestTimestamp([
            PageContent::query()->max('updated_at'),
            LocationContent::query()->max('updated_at'),
            Agency::query()->where('status', 'active')->max('updated_at'),
            app(PolicyAcceptanceService::class)->latestPublished()->max('updated_at'),
        ]);
    }

    /** @param array<int, mixed> $timestamps */
    private function latestTimestamp(array $timestamps): Carbon
    {
        $latest = collect($timestamps)
            ->filter()
            ->map(fn ($timestamp) => Carbon::parse($timestamp))
            ->sortDesc()
            ->first();

        return $latest ?? now();
    }

    /** @param Collection<int, array{url: string, lastmod: mixed}> $urls */
    private function urlSet(Collection $urls): Response
    {
        return $this->xml('sitemaps.urls', ['urls' => $urls]);
    }

    /** @param array<string, mixed> $data */
    private function xml(string $view, array $data): Response
    {
        return response()
            ->view($view, $data, 200, ['Content-Type' => 'application/xml; charset=UTF-8'])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
