<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingStatus;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Http\Requests\RequestProfileRenewalRequest;
use App\Http\Requests\UpdateOwnedProfileRequest;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Services\LocationInventoryService;
use App\Services\ProfileMediaAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProviderProfileController extends Controller
{
    public function __construct(
        private readonly ProfileMediaAccess $access,
        private readonly LocationInventoryService $locationInventory,
    ) {}

    public function show(Profile $profile): View
    {
        abort_unless($this->access->owns(request()->user(), $profile), 403);

        return view('provider.profiles.show', [
            'profile' => $profile->load([
                'primaryLocation', 'sublocation', 'microLocation', 'gender', 'ethnicity', 'build', 'bustSize',
                'details', 'contacts', 'services', 'languages', 'rates', 'rates.period',
                'images', 'currentPackageAssignment.package', 'packageRequests.requestedPackage',
            ]),
            'packages' => Package::query()->where('is_active', true)->orderBy('display_order')->get(),
            'canEdit' => in_array($profile->status, [ProfileStatus::Draft, ProfileStatus::Active], true),
            'canRenew' => in_array($profile->status, [ProfileStatus::Expired, ProfileStatus::Deactivated], true)
                && ! $profile->packageRequests()->where('status', PackageRequestStatus::Pending)->exists(),
        ]);
    }

    public function edit(Profile $profile): View
    {
        abort_unless($this->access->owns(request()->user(), $profile), 403);
        abort_unless(
            in_array($profile->status, [ProfileStatus::Draft, ProfileStatus::Active], true),
            409,
            'Private profiles cannot be edited. Request renewal from the profile page.',
        );

        return view('onboarding.profile-form', $this->formOptions($profile));
    }

    public function update(UpdateOwnedProfileRequest $request, Profile $profile): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $profile, $validated): void {
            $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
            abort_unless($this->access->owns($request->user(), $profile), 403);
            abort_unless(in_array($profile->status, [ProfileStatus::Draft, ProfileStatus::Active], true), 409);

            $locationIds = collect([
                $profile->primary_location_id,
                $profile->sublocation_id,
                $profile->micro_location_id,
            ]);
            $profileFields = [
                'display_name', 'description', 'primary_location_id', 'sublocation_id', 'micro_location_id',
                'gender_option_id', 'ethnicity_option_id', 'build_option_id',
                'bust_size_option_id', 'allows_incall', 'allows_outcall',
            ];
            $profile->fill(collect($validated)->only($profileFields)->all());
            $changedFields = array_keys($profile->getDirty());
            $profile->save();
            $locationIds
                ->merge([$profile->primary_location_id, $profile->sublocation_id, $profile->micro_location_id])
                ->filter()
                ->unique()
                ->each(fn (int $locationId) => $this->locationInventory->sync($locationId));

            $detailFields = [
                'hair_color_option_id', 'hair_length_option_id', 'height_cm', 'weight_kg',
                'smoker', 'sexual_orientation_option_id', 'website_url', 'instagram_handle',
                'snapchat_handle', 'tiktok_handle',
            ];
            $profile->details()->updateOrCreate(
                ['profile_id' => $profile->id],
                collect($validated)->only($detailFields)->all(),
            );
            $changedFields = array_values(array_unique([...$changedFields, ...$detailFields]));

            $profile->contacts()->delete();
            $profile->contacts()->createMany($this->contacts($validated));
            $profile->services()->sync($validated['service_ids']);
            $profile->languages()->sync($validated['language_ids'] ?? []);

            $profile->rates()->delete();
            if (isset($validated['rate_price'], $validated['rate_currency'], $validated['rate_period_option_id'])) {
                $profile->rates()->create([
                    'currency_code' => strtoupper($validated['rate_currency']),
                    'rate_period_option_id' => $validated['rate_period_option_id'],
                    'price' => $validated['rate_price'],
                ]);
            }

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'profiles.owner-update',
                'target_type' => 'profile',
                'target_id' => $profile->id,
                'previous_state' => ['profile_status' => $profile->status->value],
                'new_state' => [
                    'profile_status' => $profile->status->value,
                    'changed_fields' => array_values(array_unique([
                        ...$changedFields,
                        'contacts', 'services', 'languages', 'rates',
                    ])),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);
        });

        return redirect()->route('provider.profiles.show', $profile)->with('status', 'Profile updated.');
    }

    public function requestRenewal(RequestProfileRenewalRequest $request, Profile $profile): RedirectResponse
    {
        DB::transaction(function () use ($request, $profile): void {
            $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
            abort_unless($this->access->owns($request->user(), $profile), 403);
            abort_unless(
                in_array($profile->status, [ProfileStatus::Expired, ProfileStatus::Deactivated], true),
                409,
                'Only expired or deactivated profiles can request renewal.',
            );
            abort_if(
                $profile->packageRequests()->where('status', PackageRequestStatus::Pending)->exists(),
                409,
                'A renewal request is already awaiting review.',
            );

            $previousStatus = $profile->status;
            $packageRequest = $profile->packageRequests()->create([
                'requested_package_id' => $request->integer('requested_package_id'),
                'status' => PackageRequestStatus::Pending,
                'requested_by' => $request->user()->id,
                'requested_at' => now(),
            ]);
            $profile->update(['status' => ProfileStatus::PendingReview]);
            $request->user()->update([
                'onboarding_status' => OnboardingStatus::Submitted,
                'last_onboarding_activity_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'profiles.renewal-request',
                'target_type' => 'profile',
                'target_id' => $profile->id,
                'previous_state' => ['profile_status' => $previousStatus->value],
                'new_state' => [
                    'profile_status' => ProfileStatus::PendingReview->value,
                    'package_request_id' => $packageRequest->id,
                    'requested_package_id' => $packageRequest->requested_package_id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);
        });

        return redirect()->route('provider.profiles.show', $profile)
            ->with('status', 'Renewal requested. Staff will review the package and duration before reactivation.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Profile $profile): array
    {
        $profile->load(['details', 'contacts', 'services', 'languages', 'rates']);
        $taxonomies = TaxonomyOption::query()->enabled()->get()->groupBy('type');
        $contacts = $profile->contacts->keyBy('type');
        $rate = $profile->rates->first();

        return [
            'profile' => $profile,
            'form' => [
                'display_name' => $profile->display_name,
                'description' => $profile->description,
                'phone' => $contacts->get('call')?->display_value ?? $contacts->get('sms')?->display_value,
                'whatsapp_enabled' => $contacts->has('whatsapp'),
                'telegram_phone_enabled' => $contacts->has('telegram_phone'),
                'telegram_username' => $contacts->get('telegram_username')?->display_value,
                'primary_location_id' => $profile->primary_location_id,
                'sublocation_id' => $profile->sublocation_id,
                'micro_location_id' => $profile->micro_location_id,
                'gender_option_id' => $profile->gender_option_id,
                'ethnicity_option_id' => $profile->ethnicity_option_id,
                'build_option_id' => $profile->build_option_id,
                'bust_size_option_id' => $profile->bust_size_option_id,
                'date_of_birth' => $profile->date_of_birth->toDateString(),
                'allows_incall' => $profile->allows_incall,
                'allows_outcall' => $profile->allows_outcall,
                'service_ids' => $profile->services->modelKeys(),
                'language_ids' => $profile->languages->modelKeys(),
                'rate_currency' => $rate?->currency_code,
                'rate_period_option_id' => $rate?->rate_period_option_id,
                'rate_price' => $rate?->price,
                ...($profile->details?->only([
                    'hair_color_option_id', 'hair_length_option_id', 'height_cm', 'weight_kg',
                    'smoker', 'sexual_orientation_option_id', 'website_url', 'instagram_handle',
                    'snapchat_handle', 'tiktok_handle',
                ]) ?? []),
            ],
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
            'packages' => collect(),
        ];
    }

    /** @param array<string, mixed> $validated
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
