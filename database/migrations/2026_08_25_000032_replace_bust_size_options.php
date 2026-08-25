<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomy_options')) {
            return;
        }

        $options = [
            'small' => ['label' => 'Small', 'order' => 10, 'legacy' => ['a', 'b']],
            'medium' => ['label' => 'Medium', 'order' => 20, 'legacy' => ['c', 'd']],
            'large' => ['label' => 'Large', 'order' => 30, 'legacy' => ['dd', 'e']],
            'enormous' => ['label' => 'Enormous', 'order' => 40, 'legacy' => ['f', 'g-plus']],
        ];
        $activeIds = [];

        foreach ($options as $slug => $option) {
            $existing = DB::table('taxonomy_options')
                ->where('type', 'bust_size')
                ->where('slug', $slug)
                ->whereNull('country_code')
                ->first();

            if ($existing) {
                DB::table('taxonomy_options')->where('id', $existing->id)->update([
                    'label' => $option['label'],
                    'sort_order' => $option['order'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
                $targetId = $existing->id;
            } else {
                $targetId = DB::table('taxonomy_options')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'type' => 'bust_size',
                    'slug' => $slug,
                    'label' => $option['label'],
                    'country_code' => null,
                    'sort_order' => $option['order'],
                    'is_active' => true,
                    'settings' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $activeIds[] = $targetId;
            $legacyIds = DB::table('taxonomy_options')
                ->where('type', 'bust_size')
                ->whereIn('slug', $option['legacy'])
                ->pluck('id');

            if ($legacyIds->isNotEmpty() && Schema::hasTable('profiles')) {
                DB::table('profiles')->whereIn('bust_size_option_id', $legacyIds)->update([
                    'bust_size_option_id' => $targetId,
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('taxonomy_options')
            ->where('type', 'bust_size')
            ->whereNotIn('id', $activeIds)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Existing profile selections are deliberately not reverse-mapped because
        // collapsing several cup sizes into one category is not losslessly reversible.
    }
};
