<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LocationInventoryService
{
    public function __construct(private readonly DirectorySettings $settings) {}

    public function syncForProfile(Profile $profile): void
    {
        collect([$profile->primary_location_id, $profile->sublocation_id, $profile->micro_location_id])
            ->filter()
            ->unique()
            ->each(fn (int $locationId) => $this->sync($locationId));
    }

    public function sync(int $locationId): void
    {
        $location = Location::query()->find($locationId);
        if (! $location) {
            return;
        }

        $activeCount = Profile::query()
            ->publiclyVisible()
            ->when(
                $location->isMicroLocation(),
                fn (Builder $query) => $query->where('micro_location_id', $locationId),
                fn (Builder $query) => $location->parent_id
                    ? $query->where('sublocation_id', $locationId)
                    : $query->where('primary_location_id', $locationId),
            )
            ->count();

        $hasApprovedContent = DB::table('location_contents')
            ->where('location_id', $locationId)
            ->where('content_status', 'approved')
            ->exists();

        $minimumProfiles = $location->isMicroLocation()
            ? $this->settings->integer('locations.micro_min_profiles')
            : 1;

        $location->update([
            'active_profile_count' => $activeCount,
            'published_profile_count' => $activeCount,
            'is_indexable' => $location->status === 'published'
                && $hasApprovedContent
                && $activeCount >= $minimumProfiles,
        ]);
    }
}
