<?php

namespace App\Services;

use App\Models\Location;
use Closure;
use Illuminate\Support\Collection;

class LocationSidebarTree
{
    /**
     * Build a nested tree of locations for public sidebar navigation.
     *
     * A location is "active" (active_profile_count > 0) or kept as a branch
     * because one of its descendants is active — active_profile_count is
     * per-location only, not rolled up, so a city with no direct profiles
     * still needs to appear if one of its neighbourhoods has some.
     *
     * @return Collection<int, Location>
     */
    public function build(): Collection
    {
        return $this->tree(
            ['active_profile_count'],
            fn (Location $location) => $location->active_profile_count > 0,
        );
    }

    /**
     * Build a nested tree of every published + indexable location, regardless
     * of current profile count — used for the all-locations crawl page, so
     * indexable locations with zero active profiles still get an internal
     * link pointing at them somewhere on the site.
     *
     * @return Collection<int, Location>
     */
    public function buildIndexable(): Collection
    {
        return $this->tree(
            ['status', 'is_indexable'],
            fn (Location $location) => $location->status === 'published' && $location->is_indexable,
        );
    }

    /** @return Collection<int, Location> */
    private function tree(array $extraColumns, Closure $isKept): Collection
    {
        $locations = Location::query()
            ->with('content:location_id,canonical_path')
            ->orderBy('name')
            ->get([...['id', 'parent_id', 'name', 'full_slug'], ...$extraColumns]);

        return $this->branch($locations, null, $isKept);
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return Collection<int, Location>
     */
    private function branch(Collection $locations, ?int $parentId, Closure $isKept): Collection
    {
        return $locations
            ->where('parent_id', $parentId)
            ->map(fn (Location $location) => tap($location, fn (Location $l) => $l->setRelation(
                'activeChildren',
                $this->branch($locations, $l->id, $isKept),
            )))
            ->filter(fn (Location $location) => $isKept($location) || $location->activeChildren->isNotEmpty())
            ->values();
    }
}
