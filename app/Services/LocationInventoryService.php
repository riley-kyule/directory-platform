<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LocationInventoryService
{
    public function syncForProfile(Profile $profile): void
    {
        collect([$profile->primary_location_id, $profile->sublocation_id])
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
            ->where(function (Builder $query) use ($locationId): void {
                $query->where('primary_location_id', $locationId)
                    ->orWhere('sublocation_id', $locationId);
            })
            ->count();

        $hasApprovedContent = DB::table('location_contents')
            ->where('location_id', $locationId)
            ->where('content_status', 'approved')
            ->exists();

        $location->update([
            'active_profile_count' => $activeCount,
            'published_profile_count' => $activeCount,
            'is_indexable' => $location->status === 'published'
                && $hasApprovedContent
                && $activeCount >= 1,
        ]);
    }
}
