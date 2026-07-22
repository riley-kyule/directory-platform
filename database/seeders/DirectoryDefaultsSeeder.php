<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PageContent;
use App\Models\TaxonomyOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DirectoryDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        PageContent::query()->firstOrCreate(['page_key' => 'homepage'], [
            'heading' => 'Discover independent providers near you',
            'intro_content' => 'Browse active profiles by package and find the right connection for you.',
            'seo_title' => config('app.name').' — Find providers near you',
            'meta_description' => 'Browse active VIP, Premium, Basic and newly activated provider profiles.',
            'listing_sections' => [
                'vip' => ['heading' => 'VIP Escorts', 'description' => 'Featured profiles with our highest visibility package.'],
                'premium' => ['heading' => 'Premium Escorts', 'description' => 'Prominent profiles with enhanced directory visibility.'],
                'basic' => ['heading' => 'Basic Escorts', 'description' => 'All active profiles on the Basic package.'],
                'new' => ['heading' => 'New Escorts', 'description' => 'Recently activated provider profiles.'],
            ],
        ]);
        PageContent::query()->firstOrCreate(['page_key' => 'agencies'], [
            'heading' => 'Escort Agencies',
            'intro_content' => 'Browse agencies with currently active provider profiles.',
            'seo_title' => 'Escort Agencies — '.config('app.name'),
            'meta_description' => 'Browse public agencies and their currently active provider profiles.',
        ]);

        foreach ([
            ['code' => 'vip', 'name' => 'VIP', 'image_limit' => 15, 'display_order' => 10],
            ['code' => 'premium', 'name' => 'Premium', 'image_limit' => 10, 'display_order' => 20],
            ['code' => 'basic', 'name' => 'Basic', 'image_limit' => 5, 'display_order' => 30],
        ] as $package) {
            Package::query()->updateOrCreate(['code' => $package['code']], $package + ['is_active' => true]);
        }

        foreach ([7, 14, 30, 60, 90, 180, 365] as $index => $days) {
            DB::table('package_duration_options')->updateOrInsert(
                ['duration_days' => $days],
                [
                    'label' => $days === 365 ? '1 year' : $days.' days',
                    'display_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $taxonomies = [
            'gender' => [
                ['slug' => 'woman', 'label' => 'Woman', 'settings' => ['requires_bust_size' => true]],
                ['slug' => 'man', 'label' => 'Man'],
                ['slug' => 'trans-woman', 'label' => 'Trans Woman', 'settings' => ['requires_bust_size' => true]],
                ['slug' => 'trans-man', 'label' => 'Trans Man'],
                ['slug' => 'non-binary', 'label' => 'Non-binary'],
            ],
            'build' => [
                ['slug' => 'slim', 'label' => 'Slim'],
                ['slug' => 'athletic', 'label' => 'Athletic'],
                ['slug' => 'average', 'label' => 'Average'],
                ['slug' => 'curvy', 'label' => 'Curvy'],
                ['slug' => 'plus-size', 'label' => 'Plus Size'],
                ['slug' => 'muscular', 'label' => 'Muscular'],
            ],
            'hair_color' => $this->options(['Black', 'Brown', 'Blonde', 'Red', 'Grey', 'Other']),
            'hair_length' => $this->options(['Bald', 'Short', 'Medium', 'Long']),
            'bust_size' => $this->options(['A', 'B', 'C', 'D', 'DD', 'E', 'F', 'G+']),
            'service' => $this->options(['BDSM', 'Couples', 'Escort', 'GFE', 'Massage', 'Domination', 'BFE', 'Fetish', 'Mature']),
            'language' => $this->options(['English', 'Swahili']),
            'rate_period' => [
                ['slug' => '30-minutes', 'label' => '30 minutes'],
                ['slug' => '1-hour', 'label' => '1 hour'],
                ['slug' => '2-hours', 'label' => '2 hours'],
                ['slug' => 'overnight', 'label' => 'Overnight'],
            ],
        ];

        foreach ($taxonomies as $type => $options) {
            foreach ($options as $index => $option) {
                $taxonomyOption = TaxonomyOption::query()->firstOrNew(
                    ['type' => $type, 'slug' => $option['slug'], 'country_code' => null],
                );
                $taxonomyOption->fill([
                    'label' => $option['label'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'settings' => $option['settings'] ?? null,
                ]);
                $taxonomyOption->public_id ??= (string) Str::uuid();
                $taxonomyOption->save();
            }
        }
    }

    /**
     * @param  list<string>  $labels
     * @return list<array{slug: string, label: string}>
     */
    private function options(array $labels): array
    {
        return array_map(fn (string $label) => [
            'slug' => str($label)->lower()->replace('+', '-plus')->slug()->toString(),
            'label' => $label,
        ], $labels);
    }
}
