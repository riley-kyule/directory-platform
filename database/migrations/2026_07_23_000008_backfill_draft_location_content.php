<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('locations')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('location_contents')
                ->whereColumn('location_contents.location_id', 'locations.id'))
            ->orderBy('id')
            ->each(function ($location): void {
                $parent = $location->parent_id
                    ? DB::table('locations')->where('id', $location->parent_id)->first()
                    : null;
                $canonicalPath = $parent
                    ? '/'.$parent->slug.'/'.$location->slug.'-escorts'
                    : '/'.$location->slug.'-escorts';

                DB::table('location_contents')->insert([
                    'location_id' => $location->id,
                    'heading' => $location->name.' Escorts',
                    'intro_content' => '',
                    'bottom_content' => null,
                    'faq_content' => null,
                    'seo_title' => '',
                    'meta_description' => '',
                    'canonical_path' => $canonicalPath,
                    'content_status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Data backfills are intentionally retained to avoid deleting content edited after migration.
    }
};
