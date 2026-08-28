<?php

namespace App\Services;

use App\Models\DirectorySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DirectorySettings
{
    /** @var array<string, bool|int|float|string> */
    private const FALLBACKS = [
        'site.platform_name' => '',
        'site.support_email' => '',
        'site.age_gate_enabled' => false,
        'site.logo_path' => '',
        'site.favicon_path' => '',
        'seo.google_site_verification' => '',
        'seo.bing_site_verification' => '',
        'seo.profile_meta_template' => '{profile_title} is a hot {nationality} {gender} escort from {locality} in {city}, {country}. {pronoun} is available for {availability}. Hook up with {profile_title} today.',
        'navigation.primary_items' => '[{"label":"Search","url":"/search"},{"label":"Browse locations","url":"/locations"},{"label":"Browse agencies","url":"/agencies"}]',
        'security.privileged_mfa_enforced' => false,
        'profiles.agency_limit' => 15,
        'listings.new_profile_days' => 14,
        'listings.rotation_hours' => 24,
        'locations.micro_min_profiles' => 6,
        'media.maximum_file_kilobytes' => 51200,
        'media.minimum_width' => 400,
        'media.minimum_height' => 400,
        'media.maximum_dimension' => 12000,
        'media.maximum_pixels' => 40000000,
        'media.minimum_aspect_ratio' => 0.4,
        'media.maximum_aspect_ratio' => 2.5,
        'media.webp_quality' => 82,
        'media.processing_memory_limit_mb' => 512,
        'media.video_max_kilobytes' => 51200,
        'media.video_max_duration_seconds' => 120,
        'media.ffmpeg_path' => '',
        'media.ffprobe_path' => '',
    ];

    public function integer(string $key): int
    {
        return (int) $this->value($key);
    }

    public function float(string $key): float
    {
        return (float) $this->value($key);
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->value($key), FILTER_VALIDATE_BOOL);
    }

    public function string(string $key): string
    {
        return (string) $this->value($key);
    }

    /** @return list<array{label: string, url: string}> */
    public function navigationItems(): array
    {
        $items = json_decode($this->string('navigation.primary_items'), true);

        return is_array($items) ? array_values(array_filter($items, fn ($item) => is_array($item) && isset($item['label'], $item['url'])
        )) : [];
    }

    public function value(string $key): bool|int|float|string
    {
        return Cache::rememberForever(
            'directory-setting:'.$key,
            fn () => DirectorySetting::query()->find($key)?->value ?? self::FALLBACKS[$key] ?? '',
        );
    }

    /** @return array<string, bool|int|float|string> */
    public function defaults(): array
    {
        return self::FALLBACKS;
    }

    public function logoUrl(): ?string
    {
        $path = $this->string('site.logo_path');

        return $path === '' ? null : Storage::disk('branding')->url($path);
    }

    public function faviconUrl(): ?string
    {
        $path = $this->string('site.favicon_path');

        return $path === '' ? null : Storage::disk('branding')->url($path);
    }
}
