<?php

namespace App\Services;

use App\Models\Location;
use App\Models\TaxonomyOption;

class PublicSearchOptions
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        return [
            'searchCities' => Location::query()
                ->whereNull('parent_id')
                ->where('status', 'published')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'searchNeighbourhoods' => Location::query()
                ->whereNotNull('parent_id')
                ->whereNotIn('type', ['area', 'landmark'])
                ->where('status', 'published')
                ->with('parent:id,name,slug')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'slug']),
            'searchTaxonomies' => TaxonomyOption::query()
                ->whereIn('type', ['gender', 'ethnicity', 'build', 'bust_size', 'service'])
                ->enabled()
                ->get()
                ->groupBy('type'),
        ];
    }
}
