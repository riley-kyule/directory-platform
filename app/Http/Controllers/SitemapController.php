<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Location;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $maps = collect([
            ['url' => route('sitemaps.editorial'), 'lastmod' => now()],
        ]);

        $maps = $maps
            ->merge($this->chunkMaps('locations', $this->locationsQuery()->count()))
            ->merge($this->chunkMaps('profiles', Profile::query()->publiclyVisible()->count()))
            ->merge($this->chunkMaps('agencies', Agency::query()->publiclyVisible()->count()));

        return $this->xml('sitemaps.index', ['maps' => $maps]);
    }

    public function editorial(): Response
    {
        return $this->urlSet(collect([
            ['url' => route('directory.home'), 'lastmod' => now()],
            ['url' => route('directory.agencies.index'), 'lastmod' => now()],
        ]));
    }

    public function locations(int $page): Response
    {
        $locations = $this->page($this->locationsQuery()->with(['content', 'parent']), $page);

        return $this->urlSet($locations->map(fn (Location $location) => [
            'url' => url($location->content->canonical_path),
            'lastmod' => $location->content->updated_at,
        ]));
    }

    public function profiles(int $page): Response
    {
        $profiles = $this->page(Profile::query()->publiclyVisible(), $page);

        return $this->urlSet($profiles->map(fn (Profile $profile) => [
            'url' => route('directory.profiles.show', $profile->slug),
            'lastmod' => $profile->updated_at,
        ]));
    }

    public function agencies(int $page): Response
    {
        $agencies = $this->page(Agency::query()->publiclyVisible(), $page);

        return $this->urlSet($agencies->map(fn (Agency $agency) => [
            'url' => route('directory.agencies.show', $agency->slug),
            'lastmod' => $agency->updated_at,
        ]));
    }

    public function robots(): Response
    {
        return response(
            "User-agent: *\nDisallow:\n\nSitemap: ".route('sitemaps.index')."\n",
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
    private function chunkMaps(string $type, int $count): Collection
    {
        if ($count === 0) {
            return collect();
        }

        $pages = (int) ceil($count / $this->chunkSize());

        return collect(range(1, $pages))->map(fn (int $page) => [
            'url' => route('sitemaps.'.$type, $page),
            'lastmod' => now(),
        ]);
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
        return config('directory.sitemap_chunk_size');
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
