<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PublicProfileListings
{
    public function __construct(private readonly DirectorySettings $settings) {}

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
            ->where('last_activated_at', '>=', now()->subDays($this->settings->integer('listings.new_profile_days')))
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
            ->when($profile->micro_location_id, fn (Builder $query) => $query
                ->orderByRaw('CASE WHEN micro_location_id = ? THEN 0 ELSE 1 END', [$profile->micro_location_id]))
            ->orderByRaw('CASE WHEN sublocation_id = ? THEN 0 ELSE 1 END', [$profile->sublocation_id])
            ->orderBy('listing_rank')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function search(array $filters): Builder
    {
        return $this->baseQuery()
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $escaped = addcslashes($term, '\\%_');
                $query->where(fn (Builder $query) => $query
                    ->where('display_name', 'like', '%'.$escaped.'%')
                    ->orWhere('description', 'like', '%'.$escaped.'%'));
            })
            ->when($filters['city'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('primaryLocation', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['neighbourhood'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('sublocation', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['gender'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('gender', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['ethnicity'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('ethnicity', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['build'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('build', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['bust_size'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('bustSize', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['availability'] ?? null, function (Builder $query, string $availability): void {
                if (in_array($availability, ['incall', 'both'], true)) {
                    $query->where('allows_incall', true);
                }
                if (in_array($availability, ['outcall', 'both'], true)) {
                    $query->where('allows_outcall', true);
                }
            })
            ->when($filters['services'] ?? [], fn (Builder $query, array $slugs) => $query
                ->whereHas('services', fn (Builder $query) => $query->whereIn('slug', $slugs)));
    }

    private function baseQuery(?Location $location = null): Builder
    {
        return Profile::query()
            ->publiclyVisible()
            ->when($location, fn (Builder $query) => $location->isMicroLocation()
                ? $query->where('micro_location_id', $location->id)
                : ($location->parent_id
                    ? $query->where('sublocation_id', $location->id)
                    : $query->where('primary_location_id', $location->id)))
            ->with([
                'primaryLocation',
                'sublocation',
                'microLocation',
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
