<?php

namespace App\Services;

use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Models\Agency;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileCreationService
{
    /**
     * Creates a Profile (plus profile_details, contacts, services,
     * languages, an optional starting rate, and a pending package request)
     * from the same validated field set ProfileOnboardingRequest produces —
     * shared by provider self-onboarding and staff-assisted creation, which
     * differ only in who the resulting profile belongs to.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, ?int $ownerUserId, ?Agency $agency, int $requestedByUserId): Profile
    {
        return DB::transaction(function () use ($validated, $ownerUserId, $agency, $requestedByUserId): Profile {
            $profile = Profile::query()->create([
                'owner_user_id' => $ownerUserId,
                'display_name' => $validated['display_name'],
                'slug' => $this->uniqueSlug(Profile::class, $validated['display_name']),
                'description' => $validated['description'],
                'primary_location_id' => $validated['primary_location_id'],
                'sublocation_id' => $validated['sublocation_id'],
                'micro_location_id' => $validated['micro_location_id'] ?? null,
                'gender_option_id' => $validated['gender_option_id'],
                'date_of_birth' => $validated['date_of_birth'],
                'ethnicity_option_id' => $validated['ethnicity_option_id'],
                'build_option_id' => $validated['build_option_id'],
                'bust_size_option_id' => $validated['bust_size_option_id'] ?? null,
                'allows_incall' => $validated['allows_incall'],
                'allows_outcall' => $validated['allows_outcall'],
                'status' => ProfileStatus::Draft,
            ]);

            if ($agency) {
                $agency->profiles()->attach($profile, [
                    'assigned_by' => $requestedByUserId,
                    'assigned_at' => now(),
                ]);
            }

            DB::table('profile_details')->insert([
                'profile_id' => $profile->id,
                'hair_color_option_id' => $validated['hair_color_option_id'] ?? null,
                'hair_length_option_id' => $validated['hair_length_option_id'] ?? null,
                'height_cm' => $validated['height_cm'] ?? null,
                'weight_kg' => $validated['weight_kg'] ?? null,
                'smoker' => $validated['smoker'] ?? null,
                'sexual_orientation_option_id' => $validated['sexual_orientation_option_id'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'instagram_handle' => $validated['instagram_handle'] ?? null,
                'snapchat_handle' => $validated['snapchat_handle'] ?? null,
                'tiktok_handle' => $validated['tiktok_handle'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $profile->contacts()->createMany($this->contacts($validated));
            $profile->services()->sync($validated['service_ids']);
            $profile->languages()->sync($validated['language_ids'] ?? []);

            if (isset($validated['rate_price'], $validated['rate_currency'], $validated['rate_period_option_id'])) {
                DB::table('profile_rates')->insert([
                    'profile_id' => $profile->id,
                    'currency_code' => strtoupper($validated['rate_currency']),
                    'rate_period_option_id' => $validated['rate_period_option_id'],
                    'price' => $validated['rate_price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $profile->packageRequests()->create([
                'requested_package_id' => $validated['requested_package_id'],
                'status' => PackageRequestStatus::Pending,
                'requested_by' => $requestedByUserId,
                'requested_at' => now(),
            ]);

            return $profile;
        });
    }

    /** @return array<string, mixed> */
    public function formOptions(): array
    {
        $taxonomies = TaxonomyOption::query()->enabled()->get()->groupBy('type');

        return [
            'profile' => null,
            'form' => [],
            'locations' => Location::query()->whereNull('parent_id')->where('status', 'published')->orderBy('name')->get(),
            'sublocations' => Location::query()
                ->where('status', 'published')
                ->whereHas('parent', fn ($query) => $query->whereNull('parent_id'))
                ->orderBy('name')
                ->get(),
            'microLocations' => Location::query()
                ->whereIn('type', ['area', 'landmark'])
                ->where('status', 'published')
                ->orderBy('name')
                ->get(),
            'taxonomies' => $taxonomies,
            'packages' => Package::query()->where('is_active', true)->orderBy('display_order')->get(),
        ];
    }

    /** @param  class-string<Model>  $model */
    public function uniqueSlug(string $model, string $value): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @param  array<string, mixed>  $validated
     * @return array<int, array<string, mixed>>
     */
    private function contacts(array $validated): array
    {
        $contacts = [
            ['type' => 'call', 'normalized_value' => $validated['phone'], 'display_value' => $validated['phone'], 'sort_order' => 10],
            ['type' => 'sms', 'normalized_value' => $validated['phone'], 'display_value' => $validated['phone'], 'sort_order' => 20],
        ];

        if ($validated['whatsapp_enabled']) {
            $contacts[] = ['type' => 'whatsapp', 'normalized_value' => $validated['phone'], 'display_value' => $validated['phone'], 'sort_order' => 30];
        }

        if ($validated['telegram_phone_enabled']) {
            $contacts[] = ['type' => 'telegram_phone', 'normalized_value' => $validated['phone'], 'display_value' => $validated['phone'], 'sort_order' => 40];
        } elseif (! empty($validated['telegram_username'])) {
            $username = ltrim($validated['telegram_username'], '@');
            $contacts[] = ['type' => 'telegram_username', 'normalized_value' => strtolower($username), 'display_value' => '@'.$username, 'sort_order' => 40];
        }

        return array_map(fn (array $contact) => $contact + ['is_public' => true, 'is_verified' => false], $contacts);
    }
}
