<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Str;

class ProfileMetaDescription
{
    private const NATIONALITIES = [
        'BI' => 'Burundian', 'ET' => 'Ethiopian', 'GH' => 'Ghanaian', 'KE' => 'Kenyan',
        'NG' => 'Nigerian', 'RW' => 'Rwandan', 'SO' => 'Somali', 'TZ' => 'Tanzanian',
        'UG' => 'Ugandan', 'ZA' => 'South African',
    ];

    public function __construct(private readonly DirectorySettings $settings) {}

    public function for(Profile $profile): string
    {
        $countryCode = strtoupper($profile->primaryLocation->country_code);
        $country = class_exists(\Locale::class)
            ? \Locale::getDisplayRegion('-'.$countryCode, 'en')
            : $countryCode;
        $gender = $profile->gender->label;
        $genderSlug = Str::lower($profile->gender->slug ?? $gender);
        $pronoun = Str::contains($genderSlug, ['female', 'woman']) ? 'She'
            : (Str::contains($genderSlug, ['male', 'man']) ? 'He' : 'They');
        $availability = collect([
            $profile->allows_incall ? 'in-calls' : null,
            $profile->allows_outcall ? 'outcalls' : null,
        ])->filter()->join(' and ');

        $values = [
            '{profile_title}' => $profile->display_name,
            '{gender}' => $gender,
            '{locality}' => $profile->microLocation?->name ?? $profile->sublocation->name,
            '{city}' => $profile->primaryLocation->name,
            '{country}' => $country,
            '{nationality}' => self::NATIONALITIES[$countryCode] ?? $country,
            '{availability}' => $availability ?: 'appointments',
            '{services}' => $profile->services->pluck('label')->join(', '),
            '{pronoun}' => $pronoun,
        ];

        return Str::of(strtr($this->settings->string('seo.profile_meta_template'), $values))
            ->squish()->limit(320, '')->toString();
    }
}
