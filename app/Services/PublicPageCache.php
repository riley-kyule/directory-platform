<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Location;
use App\Models\Profile;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Whole-response cache for fully public, unauthenticated GET pages.
 *
 * Freshness is bounded two ways: a short TTL window everything falls back on
 * automatically (see remember()), and explicit purges triggered by model
 * events on Profile/Location/LocationContent/PageContent so moderation and
 * content changes are reflected immediately rather than waiting out the TTL.
 */
class PublicPageCache
{
    private const FRESH_SECONDS = 60;

    private const STALE_SECONDS = 600;

    /** @param  Closure(): array{status: int, content: string, content_type: string, location: string|null}  $render */
    public function remember(string $url, Closure $render): array
    {
        return Cache::flexible(
            $this->key($url),
            [self::FRESH_SECONDS, self::STALE_SECONDS],
            $render,
            ['seconds' => 10],
        );
    }

    public function forget(string $url): void
    {
        Cache::forget($this->key($url));
        Cache::forget('illuminate:cache:flexible:created:'.$this->key($url));
    }

    /**
     * Move every public URL into a fresh cache namespace. Old entries expire
     * naturally, while site-wide settings become visible immediately.
     */
    public function forgetAll(): void
    {
        Cache::forever('page-cache:generation', (string) str()->uuid());
    }

    public function forgetForProfile(Profile $profile): void
    {
        $this->forget(route('directory.profiles.show', $profile->slug));
        $this->forget(route('directory.home'));

        collect([$profile->primary_location_id, $profile->sublocation_id, $profile->micro_location_id])
            ->filter()
            ->unique()
            ->each(fn (int $locationId) => $this->forgetLocationId($locationId));

        // Queried independently (never via $profile->currentAgency) so this never
        // caches a premature miss onto the model instance the caller keeps using —
        // the same hazard documented on Location::publicPath().
        //
        // Use the qualified pivot column here, not wherePivotNull(): inside a
        // whereHas() closure the $query is a plain Eloquent builder, not the
        // BelongsToMany relation, so wherePivotNull() silently falls through to
        // dynamicWhere() and queries a literal "pivot_null" column instead. SQLite
        // tolerates the bogus column and just returns no rows; MySQL throws
        // "Unknown column 'pivot_null'" — which took down every profile save in
        // production (this runs on Profile's saved/deleted model events) since
        // local dev and the test suite both run on SQLite.
        Agency::query()
            ->whereHas('profiles', fn ($query) => $query->whereKey($profile->id)->whereNull('agency_profiles.unassigned_at'))
            ->get()
            ->each(function (Agency $agency): void {
                $this->forget(route('directory.agencies.show', $agency->slug));
                $this->forget(route('directory.agencies.index'));
            });

        // Paginated location and agency URLs are not enumerable from a single
        // model event. Rotate the namespace so no stale roster survives a
        // moderation, verification, media, package, or rank change.
        $this->forgetAll();
    }

    public function forgetForLocation(Location $location): void
    {
        $this->forget(url($location->publicPath()));
        $this->forgetAll();
    }

    public function forgetForAgency(Agency $agency): void
    {
        $this->forget(route('directory.agencies.show', $agency->slug));
        $this->forget(route('directory.agencies.index'));
        $this->forgetAll();
    }

    public function forgetLocationId(int $locationId): void
    {
        $location = Location::query()->with('content')->find($locationId);
        if ($location) {
            $this->forgetForLocation($location);
        }
    }

    private function key(string $url): string
    {
        $generation = Cache::get('page-cache:generation', 'initial');

        return 'page-cache:'.$generation.':'.$url;
    }
}
