<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Http\Requests\AgencyOnboardingRequest;
use App\Http\Requests\ProfileOnboardingRequest;
use App\Models\Agency;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProviderOnboardingController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        abort_unless($user->account_type === AccountType::Provider, 403);

        $user->load(['profile.packageRequests.requestedPackage', 'agency.profiles.packageRequests.requestedPackage']);

        return view('onboarding.index', ['user' => $user]);
    }

    public function storeAgency(AgencyOnboardingRequest $request): RedirectResponse
    {
        $agency = Agency::query()->create([
            'owner_user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'slug' => $this->uniqueSlug(Agency::class, $request->validated('name')),
            'description' => $request->validated('description'),
            'status' => 'draft',
        ]);

        $request->user()->update(['last_onboarding_activity_at' => now()]);

        return redirect()->route('onboarding.index')->with('status', "Agency {$agency->name} saved. You can now add profiles.");
    }

    public function createProfile(): View
    {
        $user = request()->user();
        abort_unless($user->account_type === AccountType::Provider, 403);

        if ($user->provider_type === ProviderType::Independent) {
            abort_if($user->profile()->exists(), 409, 'This independent account already has a profile.');
        } else {
            abort_unless($user->agency, 409, 'Complete agency registration first.');
            abort_if(
                $user->agency->profiles()->wherePivotNull('unassigned_at')->count() >= config('directory.agency_profile_limit'),
                409,
                'The agency profile limit has been reached.',
            );
        }

        return view('onboarding.profile-form', $this->formOptions());
    }

    public function storeProfile(ProfileOnboardingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $profile = Profile::query()->create([
                'owner_user_id' => $request->user()->provider_type === ProviderType::Independent
                    ? $request->user()->id
                    : null,
                'display_name' => $validated['display_name'],
                'slug' => $this->uniqueSlug(Profile::class, $validated['display_name']),
                'description' => $validated['description'],
                'primary_location_id' => $validated['primary_location_id'],
                'sublocation_id' => $validated['sublocation_id'],
                'gender_option_id' => $validated['gender_option_id'],
                'date_of_birth' => $validated['date_of_birth'],
                'ethnicity_option_id' => $validated['ethnicity_option_id'],
                'build_option_id' => $validated['build_option_id'],
                'bust_size_option_id' => $validated['bust_size_option_id'] ?? null,
                'allows_incall' => $validated['allows_incall'],
                'allows_outcall' => $validated['allows_outcall'],
                'status' => ProfileStatus::PendingReview,
            ]);

            if ($request->user()->provider_type === ProviderType::Agency) {
                $request->user()->agency->profiles()->attach($profile, [
                    'assigned_by' => $request->user()->id,
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
                'requested_by' => $request->user()->id,
                'requested_at' => now(),
            ]);

            $request->user()->update([
                'onboarding_status' => OnboardingStatus::Submitted,
                'last_onboarding_activity_at' => now(),
            ]);
        });

        return redirect()->route('onboarding.index')->with('status', 'Profile submitted for staff review.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        $taxonomies = TaxonomyOption::query()->enabled()->get()->groupBy('type');

        return [
            'locations' => Location::query()->whereNull('parent_id')->where('status', 'published')->orderBy('name')->get(),
            'sublocations' => Location::query()->whereNotNull('parent_id')->where('status', 'published')->orderBy('name')->get(),
            'taxonomies' => $taxonomies,
            'packages' => Package::query()->where('is_active', true)->orderBy('display_order')->get(),
        ];
    }

    /** @param  array<string, mixed>  $validated */
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

    /** @param  class-string<Model>  $model */
    private function uniqueSlug(string $model, string $value): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
