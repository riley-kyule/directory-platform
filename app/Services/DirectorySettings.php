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
        'security.privileged_mfa_enforced' => false,
        'profiles.agency_limit' => 15,
        'listings.new_profile_days' => 14,
        'listings.rotation_hours' => 24,
        'locations.micro_min_profiles' => 6,
        'media.maximum_file_kilobytes' => 51200,
        'media.minimum_width' => 600,
        'media.minimum_height' => 600,
        'media.maximum_dimension' => 12000,
        'media.maximum_pixels' => 40000000,
        'media.minimum_aspect_ratio' => 0.4,
        'media.maximum_aspect_ratio' => 2.5,
        'media.webp_quality' => 82,
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
