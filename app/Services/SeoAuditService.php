<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only SEO health checks: pages that exist but nothing on the site
 * links to (relying on the sitemap alone to be discovered), and pages that
 * would compete with each other for the same search intent.
 */
class SeoAuditService
{
    /**
     * A published location with no active profile has no page anywhere on
     * the site linking to it — active_profile_count is exactly the count of
     * profiles whose breadcrumb chain includes this location, at any depth,
     * so zero means genuinely no inbound internal link.
     *
     * @return Collection<int, Location>
     */
    public function orphanLocations(): Collection
    {
        return Location::query()
            ->where('status', 'published')
            ->where('active_profile_count', 0)
            ->with('parent')
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<string, Collection<int, LocationContent>> */
    public function duplicateSeoTitles(): Collection
    {
        return $this->duplicatesOf('seo_title');
    }

    /** @return Collection<string, Collection<int, LocationContent>> */
    public function duplicateMetaDescriptions(): Collection
    {
        return $this->duplicatesOf('meta_description');
    }

    /** @return Collection<string, Collection<int, LocationContent>> */
    private function duplicatesOf(string $column): Collection
    {
        $duplicateValues = LocationContent::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->having('total', '>', 1)
            ->pluck($column);

        if ($duplicateValues->isEmpty()) {
            return collect();
        }

        return LocationContent::query()
            ->whereIn($column, $duplicateValues)
            ->with('location:id,name,full_slug,status')
            ->get()
            ->groupBy($column);
    }
}
