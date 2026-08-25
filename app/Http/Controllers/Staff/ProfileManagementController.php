<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageProfileLifecycleRequest;
use App\Jobs\PublishProfileImages;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Models\Profile;
use App\Services\LocationInventoryService;
use App\Services\PolicyAcceptanceService;
use App\Services\ProfileImageVisibility;
use App\Services\ProfileVerificationService;
use App\Services\PublicProfileListings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileManagementController extends Controller
{
    public function __construct(
        private readonly PublicProfileListings $listings,
        private readonly LocationInventoryService $locationInventory,
        private readonly ProfileImageVisibility $imageVisibility,
        private readonly PolicyAcceptanceService $policies,
        private readonly ProfileVerificationService $verification,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('profiles.view-private');

        $search = Str::of((string) $request->query('q', ''))->trim()->limit(100, '')->toString();

        return view('staff.directory.index', [
            'sections' => [
                'vip' => $this->searchProfiles($this->listings->forPackage('vip'), $search)->paginate(12, ['*'], 'vip_page'),
                'premium' => $this->searchProfiles($this->listings->forPackage('premium'), $search)->paginate(12, ['*'], 'premium_page'),
                'basic' => $this->searchProfiles($this->listings->forPackage('basic'), $search)->paginate(12, ['*'], 'basic_page'),
                'new' => $this->searchProfiles($this->listings->newProfiles(), $search)->paginate(12, ['*'], 'new_page'),
                'private' => $this->searchProfiles($this->privateProfiles(), $search)->paginate(20, ['*'], 'private_page'),
            ],
            'search' => $search,
        ]);
    }

    public function show(Request $request, Profile $profile): View
    {
        Gate::authorize('profiles.view-private');

        $profile->load([
            'primaryLocation', 'sublocation', 'microLocation', 'owner', 'currentAgency.owner', 'contacts', 'images',
            'packageAssignments.package', 'services',
        ]);
        if ($profile->status === ProfileStatus::Draft) {
            $profile->load('packageRequests.requestedPackage');
        }

        return view('staff.directory.show', [
            'profile' => $profile,
            'packages' => Package::query()->where('is_active', true)->orderBy('display_order')->get(),
            'durations' => PackageDurationOption::query()->where('is_active', true)->orderBy('display_order')->get(),
            'submissionPolicies' => $profile->status === ProfileStatus::Draft
                ? $this->policies->outstanding('profile_submission', $request->user(), $profile)
                : collect(),
            'audits' => AuditLog::query()
                ->where('target_type', 'profile')
                ->where('target_id', $profile->id)
                ->with('actor')
                ->latest()
                ->limit(25)
                ->get(),
        ]);
    }

    public function update(ManageProfileLifecycleRequest $request, Profile $profile): RedirectResponse
    {
        DB::transaction(function () use ($request, $profile): void {
            $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
            $action = $request->validated('action');
            $previousStatus = $profile->status;
            $previousAssignment = $profile->packageAssignments()->latest('starts_at')->first();

            match ($action) {
                'deactivate' => $this->makePrivate($profile, ProfileStatus::Deactivated, 'deactivated'),
                'remove_package' => $this->makePrivate($profile, ProfileStatus::Deactivated, 'removed'),
                'ban' => $this->ban($profile),
                'renew' => $this->renew($request, $profile, $previousAssignment?->id),
                'assign_package' => $this->assignPackageOverride($request, $profile, $previousAssignment?->id),
            };

            $profile->refresh();
            $this->locationInventory->syncForProfile($profile);
            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'profiles.'.$action,
                'target_type' => 'profile',
                'target_id' => $profile->id,
                'previous_state' => [
                    'profile_status' => $previousStatus->value,
                    'assignment_id' => $previousAssignment?->id,
                    'package_id' => $previousAssignment?->package_id,
                ],
                'new_state' => [
                    'profile_status' => $profile->status->value,
                    'assignment_id' => $profile->packageAssignments()->latest('starts_at')->value('id'),
                    'package_id' => $profile->packageAssignments()->latest('starts_at')->value('package_id'),
                    'expires_at' => $profile->expires_at?->toIso8601String(),
                    'requirements_override_used' => $request->boolean('override_requirements'),
                ],
                'reason' => $request->validated('reason'),
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);
        });

        return redirect()->route('staff.directory.show', $profile)->with('status', 'Profile lifecycle updated.');
    }

    private function privateProfiles(): Builder
    {
        return Profile::query()
            ->where(function (Builder $query): void {
                $query->where('status', '!=', ProfileStatus::Active->value)
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now()))
                    ->orWhereDoesntHave('packageAssignments', fn (Builder $query) => $query
                        ->where('status', 'active')
                        ->where('expires_at', '>', now()));
            })
            ->with(['primaryLocation', 'sublocation', 'microLocation', 'owner', 'currentAgency.owner', 'currentPackageAssignment.package', 'packageAssignments.package'])
            ->latest('updated_at');
    }

    private function searchProfiles(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $term = '%'.addcslashes($search, '\\%_').'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('display_name', 'like', $term)
                ->orWhere('slug', 'like', $term)
                ->orWhere('public_id', 'like', $term)
                ->orWhereHas('owner', fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term))
                ->orWhereHas('primaryLocation', fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('full_slug', 'like', $term))
                ->orWhereHas('sublocation', fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('full_slug', 'like', $term))
                ->orWhereHas('microLocation', fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('full_slug', 'like', $term));
        });
    }

    private function makePrivate(Profile $profile, ProfileStatus $status, string $assignmentStatus): void
    {
        abort_unless($profile->status === ProfileStatus::Active, 409, 'Only an active profile can be made private.');
        $this->imageVisibility->unpublish($profile);
        $profile->packageAssignments()->where('status', 'active')->update(['status' => $assignmentStatus]);
        $profile->update(['status' => $status]);
    }

    private function ban(Profile $profile): void
    {
        abort_if($profile->status === ProfileStatus::Banned, 409, 'This profile is already banned.');
        if ($profile->status === ProfileStatus::Active) {
            $this->imageVisibility->unpublish($profile);
        }
        $profile->packageAssignments()->where('status', 'active')->update(['status' => 'banned']);
        $profile->update(['status' => ProfileStatus::Banned]);
    }

    private function renew(ManageProfileLifecycleRequest $request, Profile $profile, ?int $previousAssignmentId): void
    {
        abort_unless(
            in_array($profile->status, [ProfileStatus::Expired, ProfileStatus::Deactivated], true),
            409,
            'Only an expired or deactivated profile can be renewed.',
        );
        $override = $request->boolean('override_requirements');
        if ($override) {
            abort_unless($request->user()->canOverrideListingRequirements(), 403, 'Only an Admin or CSR may override listing requirements.');
        }
        abort_if(
            ! $profile->images()->whereIn('status', ['pending_review', 'approved'])->exists() && ! $override,
            422,
            'At least one reviewed image is required.',
        );
        $missingVerificationTypes = $this->verification->missingTypes($profile);
        abort_if($missingVerificationTypes !== [] && ! $override, 422, 'Complete every required verification check or use an authorized staff override.');
        if ($missingVerificationTypes !== []) {
            $this->verification->override($profile, $request->user(), $request->validated('reason'));
        } else {
            $this->verification->sync($profile);
        }

        $duration = PackageDurationOption::query()->where('is_active', true)->findOrFail($request->integer('duration_option_id'));
        $startsAt = now();
        $expiresAt = $startsAt->copy()->addDays($duration->duration_days);
        $profile->packageAssignments()->where('status', 'active')->update(['status' => 'superseded']);
        $profile->packageAssignments()->create([
            'package_id' => $request->integer('package_id'),
            'previous_assignment_id' => $previousAssignmentId,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => 'active',
            'assigned_by' => $request->user()->id,
            'assignment_source' => 'manual_renewal',
            'reason' => $request->validated('reason'),
        ]);
        $profile->update([
            'status' => ProfileStatus::Active,
            'last_activated_at' => $startsAt,
            'expires_at' => $expiresAt,
            'listing_rank' => random_int(1, 2_147_483_647),
        ]);
        PublishProfileImages::dispatch($profile->id)->afterCommit();
    }

    private function assignPackageOverride(ManageProfileLifecycleRequest $request, Profile $profile, ?int $previousAssignmentId): void
    {
        abort_unless($request->user()->canOverrideListingRequirements(), 403, 'Only an Admin or CSR may assign a package by override.');
        abort_if($profile->status === ProfileStatus::Banned, 409, 'A banned profile must complete the moderation appeal workflow before activation.');

        $missingVerificationTypes = $this->verification->missingTypes($profile);
        if ($missingVerificationTypes !== []) {
            $this->verification->override($profile, $request->user(), $request->validated('reason'));
        } else {
            $this->verification->sync($profile);
        }

        $duration = PackageDurationOption::query()->where('is_active', true)->findOrFail($request->integer('duration_option_id'));
        $startsAt = now();
        $expiresAt = $startsAt->copy()->addDays($duration->duration_days);
        $profile->packageAssignments()->where('status', 'active')->update(['status' => 'superseded']);
        $profile->packageAssignments()->create([
            'package_id' => $request->integer('package_id'),
            'previous_assignment_id' => $previousAssignmentId,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => 'active',
            'assigned_by' => $request->user()->id,
            'assignment_source' => 'staff_override',
            'reason' => $request->validated('reason'),
        ]);
        $profile->packageRequests()->where('status', 'pending')->update([
            'status' => 'changed',
            'reviewed_by' => $request->user()->id,
            'assigned_package_id' => $request->integer('package_id'),
            'decision_reason' => $request->validated('reason'),
            'reviewed_at' => now(),
        ]);
        $profile->update([
            'status' => ProfileStatus::Active,
            'published_at' => $profile->published_at ?? $startsAt,
            'last_activated_at' => $startsAt,
            'expires_at' => $expiresAt,
            'listing_rank' => random_int(1, 2_147_483_647),
        ]);
        PublishProfileImages::dispatch($profile->id)->afterCommit();
    }
}
