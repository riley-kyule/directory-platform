<?php

namespace App\Http\Controllers\Staff;

use App\Enums\OnboardingStatus;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewProfileRequest;
use App\Jobs\PublishProfileImages;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Models\ProfilePackageRequest;
use App\Services\LocationInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileReviewController extends Controller
{
    public function __construct(private readonly LocationInventoryService $locationInventory) {}

    public function index(): View
    {
        Gate::authorize('profiles.activate');

        return view('staff.profiles.index', [
            'requests' => ProfilePackageRequest::query()
                ->where('status', PackageRequestStatus::Pending)
                ->whereHas('profile', fn ($query) => $query->where('status', ProfileStatus::PendingReview))
                ->with(['profile.primaryLocation', 'requestedPackage', 'requestedBy'])
                ->oldest('requested_at')
                ->paginate(25),
        ]);
    }

    public function show(ProfilePackageRequest $packageRequest): View
    {
        Gate::authorize('profiles.activate');
        abort_unless($packageRequest->status === PackageRequestStatus::Pending, 404);

        return view('staff.profiles.show', [
            'packageRequest' => $packageRequest->load([
                'profile.primaryLocation', 'profile.sublocation', 'profile.microLocation', 'profile.contacts', 'profile.images',
                'profile.services', 'requestedPackage', 'requestedBy',
            ]),
            'packages' => Package::query()->where('is_active', true)->orderBy('display_order')->get(),
            'durations' => PackageDurationOption::query()->where('is_active', true)->orderBy('display_order')->get(),
        ]);
    }

    public function update(ReviewProfileRequest $request, ProfilePackageRequest $packageRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $packageRequest): void {
            $packageRequest = ProfilePackageRequest::query()->lockForUpdate()->findOrFail($packageRequest->id);
            abort_unless($packageRequest->status === PackageRequestStatus::Pending, 409, 'This request has already been reviewed.');

            $profile = $packageRequest->profile()->lockForUpdate()->firstOrFail();
            $previousState = [
                'profile_status' => $profile->status->value,
                'request_status' => $packageRequest->status->value,
                'requested_package_id' => $packageRequest->requested_package_id,
            ];

            if ($request->validated('decision') === 'reject') {
                $packageRequest->update([
                    'status' => PackageRequestStatus::Rejected,
                    'reviewed_by' => $request->user()->id,
                    'decision_reason' => $request->validated('reason'),
                    'reviewed_at' => now(),
                ]);
                $profile->update(['status' => ProfileStatus::Rejected]);

                $this->audit($request->user()->id, 'profiles.reject', $profile->id, $previousState, [
                    'profile_status' => ProfileStatus::Rejected->value,
                    'request_status' => PackageRequestStatus::Rejected->value,
                ], $request->validated('reason'));

                return;
            }

            abort_unless(
                $profile->images()->whereIn('status', ['pending_review', 'approved'])->exists(),
                422,
                'At least one successfully processed image is required for activation.',
            );

            $duration = PackageDurationOption::query()->where('is_active', true)->findOrFail($request->integer('duration_option_id'));
            $packageId = $request->integer('assigned_package_id');
            $startsAt = now();
            $expiresAt = $startsAt->copy()->addDays($duration->duration_days);

            $profile->packageAssignments()->where('status', 'active')->update(['status' => 'superseded']);
            $previousAssignment = $profile->packageAssignments()->latest('id')->first();
            $assignment = $profile->packageAssignments()->create([
                'package_id' => $packageId,
                'request_id' => $packageRequest->id,
                'previous_assignment_id' => $previousAssignment?->id,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'assigned_by' => $request->user()->id,
                'assignment_source' => 'manual',
                'reason' => $request->validated('reason'),
            ]);

            $requestStatus = $packageId === $packageRequest->requested_package_id
                ? PackageRequestStatus::Approved
                : PackageRequestStatus::Changed;

            $packageRequest->update([
                'status' => $requestStatus,
                'reviewed_by' => $request->user()->id,
                'assigned_package_id' => $packageId,
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
            $this->locationInventory->syncForProfile($profile);

            $agency = $profile->agency()->wherePivotNull('unassigned_at')->first();
            $agency?->update(['status' => 'active']);
            $owner = $profile->owner ?? $agency?->owner;
            $owner?->update([
                'onboarding_status' => OnboardingStatus::Completed,
                'onboarding_completed_at' => now(),
                'last_onboarding_activity_at' => now(),
            ]);

            $this->audit($request->user()->id, 'profiles.activate', $profile->id, $previousState, [
                'profile_status' => ProfileStatus::Active->value,
                'request_status' => $requestStatus->value,
                'assignment_id' => $assignment->id,
                'assigned_package_id' => $packageId,
                'expires_at' => $expiresAt->toIso8601String(),
            ], $request->validated('reason'));

            PublishProfileImages::dispatch($profile->id)->afterCommit();
        });

        return redirect()->route('staff.profiles.index')->with('status', 'Profile review completed.');
    }

    /** @param  array<string, mixed>  $previousState
     * @param  array<string, mixed>  $newState
     */
    private function audit(int $actorId, string $action, int $profileId, array $previousState, array $newState, string $reason): void
    {
        $requestId = request()->header('X-Request-ID');

        AuditLog::query()->create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => 'profile',
            'target_id' => $profileId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'request_id' => is_string($requestId) && Str::isUuid($requestId) ? $requestId : null,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => str(request()->userAgent())->limit(500)->toString(),
        ]);
    }
}
