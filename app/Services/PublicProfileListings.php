<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PublicProfileListings
{
    /**
     * @return array<string, Collection<int, Profile>>
     */
    public function sections(?Location $location = null, int $limit = 12): array
    {
        return [
            'vip' => $this->forPackage('vip', $location)->limit($limit)->get(),
            'premium' => $this->forPackage('premium', $location)->limit($limit)->get(),
            'basic' => $this->forPackage('basic', $location)->limit($limit)->get(),
            'new' => $this->newProfiles($location)->limit($limit)->get(),
        ];
    }

    public function forPackage(string $packageCode, ?Location $location = null): Builder
    {
        return $this->baseQuery($location)
            ->whereHas('packageAssignments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->whereHas('package', fn (Builder $query) => $query->where('code', $packageCode)));
    }

    public function newProfiles(?Location $location = null): Builder
    {
        return $this->baseQuery($location)
            ->where('last_activated_at', '>=', now()->subDays(config('directory.new_profile_days')))
            ->whereHas('packageAssignments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('expires_at', '>', now()));
    }

    public function relatedTo(Profile $profile, int $limit = 4): Collection
    {
        return $this->baseQuery()
            ->whereKeyNot($profile->id)
            ->where('primary_location_id', $profile->primary_location_id)
            ->whereHas('packageAssignments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('expires_at', '>', now()))
            ->reorder()
            ->orderByRaw('CASE WHEN sublocation_id = ? THEN 0 ELSE 1 END', [$profile->sublocation_id])
            ->orderBy('listing_rank')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function baseQuery(?Location $location = null): Builder
    {
        return Profile::query()
            ->publiclyVisible()
            ->when($location, fn (Builder $query) => $location->parent_id
                ? $query->where('sublocation_id', $location->id)
                : $query->where('primary_location_id', $location->id))
            ->with([
                'primaryLocation',
                'sublocation',
                'owner',
                'currentAgency.owner',
                'images' => fn ($query) => $query->where('status', 'approved')->limit(1),
                'contacts' => fn ($query) => $query->where('is_public', true),
                'currentPackageAssignment.package',
            ])
            ->orderBy('listing_rank')
            ->orderBy('id');
    }
}
