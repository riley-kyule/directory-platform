<?php

namespace Database\Seeders;

use App\Models\DirectorySetting;
use App\Models\MailSetting;
use App\Models\Package;
use App\Models\PageContent;
use App\Models\PolicyVersion;
use App\Models\TaxonomyOption;
use App\Services\ContentHtml;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DirectoryDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $contentHtml = app(ContentHtml::class);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        MailSetting::query()->firstOrCreate(['id' => 1], [
            'mailer' => 'sendmail',
            'from_address' => 'no-reply@'.$host,
            'from_name' => config('app.name'),
            'sendmail_path' => '/usr/sbin/sendmail -bs -i',
        ]);

        foreach ([
            ['key' => 'security.privileged_mfa_enforced', 'value' => '0', 'value_type' => 'boolean', 'group' => 'security'],
            ['key' => 'profiles.agency_limit', 'value' => '15', 'value_type' => 'integer', 'group' => 'profiles'],
            ['key' => 'listings.new_profile_days', 'value' => '14', 'value_type' => 'integer', 'group' => 'listings'],
            ['key' => 'listings.rotation_hours', 'value' => '24', 'value_type' => 'integer', 'group' => 'listings'],
            ['key' => 'locations.micro_min_profiles', 'value' => '6', 'value_type' => 'integer', 'group' => 'locations'],
            ['key' => 'media.maximum_file_kilobytes', 'value' => '51200', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.minimum_width', 'value' => '400', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.minimum_height', 'value' => '400', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.maximum_dimension', 'value' => '12000', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.maximum_pixels', 'value' => '40000000', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.minimum_aspect_ratio', 'value' => '0.4', 'value_type' => 'decimal', 'group' => 'media'],
            ['key' => 'media.maximum_aspect_ratio', 'value' => '2.5', 'value_type' => 'decimal', 'group' => 'media'],
            ['key' => 'media.webp_quality', 'value' => '82', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.processing_memory_limit_mb', 'value' => '512', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.video_max_kilobytes', 'value' => '51200', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.video_max_duration_seconds', 'value' => '120', 'value_type' => 'integer', 'group' => 'media'],
            ['key' => 'media.ffmpeg_path', 'value' => '', 'value_type' => 'string', 'group' => 'media'],
            ['key' => 'media.ffprobe_path', 'value' => '', 'value_type' => 'string', 'group' => 'media'],
        ] as $setting) {
            DirectorySetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
            // DatabaseSeeder runs WithoutModelEvents, so DirectorySetting::saved
            // never fires here — flush the read-through cache by hand or a deploy
            // that changes a default keeps serving the stale value.
            Cache::forget('directory-setting:'.$setting['key']);
        }

        PageContent::query()->firstOrCreate(['page_key' => 'homepage'], [
            'heading' => 'Discover independent providers near you',
            'intro_content' => '<p>Browse active profiles by package and find the right connection for you.</p>',
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
            'intro_content' => '<p>Browse agencies with currently active provider profiles.</p>',
            'seo_title' => 'Escort Agencies — '.config('app.name'),
            'meta_description' => 'Browse public agencies and their currently active provider profiles.',
        ]);

        // Ships a real, published, generic legal policy per type so a fresh
        // install has working policy pages immediately — review with
        // qualified legal counsel before relying on these for launch.
        foreach (PolicyTemplates::all() as $type => $template) {
            $policyHtml = $contentHtml->fromMarkdown($template['content']);
            PolicyVersion::query()->firstOrCreate(
                ['policy_type' => $type, 'version' => $template['version']],
                [
                    'title' => $template['title'],
                    'summary' => $template['summary'],
                    'content' => $policyHtml,
                    'content_hash' => hash('sha256', $policyHtml),
                    'requires_reacceptance' => $template['requires_reacceptance'],
                    'published_at' => now(),
                ],
            );
        }

        foreach ([
            ['code' => 'vip', 'name' => 'VIP', 'image_limit' => 15, 'video_limit' => 5, 'display_order' => 10],
            ['code' => 'premium', 'name' => 'Premium', 'image_limit' => 10, 'video_limit' => 3, 'display_order' => 20],
            ['code' => 'basic', 'name' => 'Basic', 'image_limit' => 5, 'video_limit' => 1, 'display_order' => 30],
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
            'bust_size' => $this->options(['Small', 'Medium', 'Large', 'Enormous']),
            // 11-profile-fields.md marks ethnicity a *required* controlled option but
            // explicitly deployment-specific — this is a functional starter set only
            // (onboarding is impossible with zero options, which is the actual bug
            // this fixes), not a claim about the right taxonomy. Review and adjust
            // via the admin taxonomy tools before launch.
            'ethnicity' => $this->options(['African', 'Arab', 'Asian', 'Caucasian/White', 'Indian', 'Latina', 'Mixed race', 'Other']),
            'sexual_orientation' => $this->options(['Straight', 'Gay', 'Lesbian', 'Bisexual', 'Pansexual', 'Other']),
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
