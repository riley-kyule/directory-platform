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
use App\Models\Profile;
use App\Models\User;
use App\Services\DirectorySettings;
use App\Services\PolicyAcceptanceService;
use App\Services\ProfileCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProviderOnboardingController extends Controller
{
    public function __construct(
        private readonly DirectorySettings $settings,
        private readonly PolicyAcceptanceService $policies,
        private readonly ProfileCreationService $profileCreation,
    ) {}

    public function index(): View
    {
        $user = request()->user();
        abort_unless($user->account_type === AccountType::Provider, 403);

        $user->load([
            'profile.packageRequests.requestedPackage', 'profile.images',
            'agency.profiles.packageRequests.requestedPackage', 'agency.profiles.images',
        ]);
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
            'slug' => $this->profileCreation->uniqueSlug(Agency::class, $request->validated('name')),
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

        return view('onboarding.profile-form', $this->profileCreation->formOptions());
    }

    public function storeProfile(ProfileOnboardingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $this->profileCreation->create(
                $validated,
                ownerUserId: $request->user()->provider_type === ProviderType::Independent ? $request->user()->id : null,
                agency: $request->user()->provider_type === ProviderType::Agency ? $request->user()->agency : null,
                requestedByUserId: $request->user()->id,
            );

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
        if (! $profile->images()->whereIn('status', ['pending_review', 'reviewed', 'approved'])->exists()) {
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

    /**
     * True ownership (owner_user_id match, or agency attachment) OR a staff
     * member with profiles.create — same shape as ProfileMediaAccess::owns()
     * plus its own permission bypass, so staff can carry a listing they
     * created on someone else's behalf through to submission.
     */
    private function ownsProfile(User $user, Profile $profile): bool
    {
        if ($user->hasPermission('profiles.create')) {
            return true;
        }

        if ($profile->owner_user_id === $user->id) {
            return true;
        }

        return $user->agency?->profiles()
            ->whereKey($profile->id)
            ->wherePivotNull('unassigned_at')
            ->exists() ?? false;
    }
}
