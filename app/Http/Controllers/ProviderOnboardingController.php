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
use App\Models\User;
use App\Services\DirectorySettings;
use App\Services\PolicyAcceptanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProviderOnboardingController extends Controller
{
    public function __construct(
        private readonly DirectorySettings $settings,
        private readonly PolicyAcceptanceService $policies,
    ) {}

    public function index(): View
    {
        $user = request()->user();
        abort_unless($user->account_type === AccountType::Provider, 403);

        $user->load(['profile.packageRequests.requestedPackage', 'agency.profiles.packageRequests.requestedPackage']);
        $profiles = collect([$user->profile])
            ->merge($user->agency?->profiles ?? [])
            ->filter();

        return view('onboarding.index', [
            'user' => $user,
            'agencyProfileLimit' => $this->settings->integer('profiles.agency_limit'),
            'submissionPolicies' => $profiles->mapWithKeys(fn (Profile $profile) => [
                $profile->id => $this->policies->outstanding('profile_submission', $user, $profile),
            ]),
        ]);
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
                $user->agency->profiles()->wherePivotNull('unassigned_at')->count() >= $this->settings->integer('profiles.agency_limit'),
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
                'onboarding_status' => OnboardingStatus::InProgress,
                'last_onboarding_activity_at' => now(),
            ]);
        });

        return redirect()->route('onboarding.index')->with('status', 'Profile draft saved. Add media, then submit it for review.');
    }

    public function submitProfile(Request $request, Profile $profile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->ownsProfile($user, $profile), 403);
        abort_unless($profile->status === ProfileStatus::Draft, 409, 'Only a draft profile can be submitted.');
        abort_unless($profile->packageRequests()->where('status', PackageRequestStatus::Pending)->exists(), 409, 'Choose a package before submitting.');
        if (! $profile->images()->whereIn('status', ['pending_review', 'approved'])->exists()) {
            return back()->withErrors(['media' => 'Upload at least one image and wait for processing to finish before submitting.']);
        }

        $selected = $request->validate([
            'policy_acceptances' => ['nullable', 'array'],
            'policy_acceptances.*' => ['integer'],
        ])['policy_acceptances'] ?? [];
        if (! $this->policies->allRequiredSelected('profile_submission', $selected, $user, $profile)) {
            throw ValidationException::withMessages([
                'policy_acceptances' => 'Accept every required provider policy before submitting this profile.',
            ]);
        }
        $accepted = $this->policies->acceptedSelection('profile_submission', $selected, $user, $profile);

        DB::transaction(function () use ($request, $profile, $user, $accepted): void {
            $profile->update(['status' => ProfileStatus::PendingReview]);
            $user->update([
                'onboarding_status' => OnboardingStatus::Submitted,
                'last_onboarding_activity_at' => now(),
            ]);
            $this->policies->record($user, 'profile_submission', $accepted, $request, $profile);
        });

        return redirect()->route('onboarding.index')->with('status', 'Profile submitted for staff review.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
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

    private function ownsProfile(User $user, Profile $profile): bool
    {
        if ($profile->owner_user_id === $user->id) {
            return true;
        }

        return $user->agency?->profiles()
            ->whereKey($profile->id)
            ->wherePivotNull('unassigned_at')
            ->exists() ?? false;
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
