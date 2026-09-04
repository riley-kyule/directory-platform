<?php

namespace App\Services;

use App\Models\Profile;
use App\Support\CountryName;
use Illuminate\Support\Str;

class ProfileMetaDescription
{
    /** ISO 3166-1 alpha-2 → English demonym. Missing codes just render nothing. */
    private const NATIONALITIES = [
        'AE' => 'Emirati', 'SA' => 'Saudi', 'QA' => 'Qatari', 'KW' => 'Kuwaiti', 'BH' => 'Bahraini',
        'OM' => 'Omani', 'JO' => 'Jordanian', 'LB' => 'Lebanese', 'EG' => 'Egyptian', 'MA' => 'Moroccan',
        'TN' => 'Tunisian', 'DZ' => 'Algerian', 'TR' => 'Turkish', 'IN' => 'Indian', 'PK' => 'Pakistani',
        'BD' => 'Bangladeshi', 'LK' => 'Sri Lankan', 'NP' => 'Nepali', 'PH' => 'Filipino', 'ID' => 'Indonesian',
        'TH' => 'Thai', 'VN' => 'Vietnamese', 'CN' => 'Chinese', 'RU' => 'Russian', 'UA' => 'Ukrainian',
        'GB' => 'British', 'IE' => 'Irish', 'FR' => 'French', 'DE' => 'German', 'IT' => 'Italian',
        'ES' => 'Spanish', 'PT' => 'Portuguese', 'PL' => 'Polish', 'RO' => 'Romanian', 'NL' => 'Dutch',
        'US' => 'American', 'CA' => 'Canadian', 'BR' => 'Brazilian', 'CO' => 'Colombian', 'VE' => 'Venezuelan',
        'BI' => 'Burundian', 'ET' => 'Ethiopian', 'GH' => 'Ghanaian', 'KE' => 'Kenyan', 'NG' => 'Nigerian',
        'RW' => 'Rwandan', 'SO' => 'Somali', 'TZ' => 'Tanzanian', 'UG' => 'Ugandan', 'ZA' => 'South African',
    ];

    public function __construct(private readonly DirectorySettings $settings) {}

    public function for(Profile $profile): string
    {
        $primary = $profile->primaryLocation;
        $countryCode = strtoupper((string) $primary->country_code);

        // The "city" is the location the provider actually chose, exactly as the
        // SEO team typed it — the primary unless that primary IS the country,
        // in which case fall to the sub-location. Never an ISO code.
        $city = $primary->type === 'country'
            ? $profile->sublocation->name
            : $primary->name;

        // Country name: a real display name if the code resolves, otherwise the
        // top-level location name — but never the raw two-letter code.
        $country = CountryName::resolve($countryCode)
            ?? ($primary->type === 'country' ? $primary->name : '');

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
            '{city}' => $city,
            '{country}' => $country,
            '{nationality}' => self::NATIONALITIES[$countryCode] ?? '',
            '{availability}' => $availability ?: 'appointments',
            '{services}' => $profile->services->pluck('label')->join(', '),
            '{pronoun}' => $pronoun,
        ];

        // A template does not have to use every token. Substitute, then tidy the
        // punctuation an omitted token leaves behind ("in Dubai, . She" etc.).
        return Str::of(strtr($this->settings->string('seo.profile_meta_template'), $values))
            ->replaceMatches('/\s*,(\s*,)+/', ',')      // ", ," -> ","
            ->replaceMatches('/\s*,\s*(?=[.!?])/', '')  // ", ." -> "."
            ->replaceMatches('/\(\s*\)/', '')           // "()" left by an empty token
            ->replaceMatches('/\s+([.,!?;:])/', '$1')   // " ." -> "."
            ->replaceMatches('/([.!?])\s*\1+/', '$1')   // ".." -> "."
            ->squish()
            ->limit(320, '')
            ->toString();
    }
}
