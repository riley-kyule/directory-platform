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
use App\Services\ProfileImageVisibility;
use App\Services\PublicProfileListings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProfileManagementController extends Controller
{
    public function __construct(
        private readonly PublicProfileListings $listings,
        private readonly LocationInventoryService $locationInventory,
        private readonly ProfileImageVisibility $imageVisibility,
    ) {}

    public function index(): View
    {
        Gate::authorize('profiles.view-private');

        return view('staff.directory.index', [
            'sections' => [
                'vip' => $this->listings->forPackage('vip')->paginate(12, ['*'], 'vip_page'),
                'premium' => $this->listings->forPackage('premium')->paginate(12, ['*'], 'premium_page'),
                'basic' => $this->listings->forPackage('basic')->paginate(12, ['*'], 'basic_page'),
                'new' => $this->listings->newProfiles()->paginate(12, ['*'], 'new_page'),
                'private' => $this->privateProfiles()->paginate(20, ['*'], 'private_page'),
            ],
        ]);
    }

    public function show(Profile $profile): View
    {
        Gate::authorize('profiles.view-private');

        return view('staff.directory.show', [
            'profile' => $profile->load([
                'primaryLocation', 'sublocation', 'owner', 'currentAgency.owner', 'contacts', 'images',
                'packageAssignments.package', 'services',
            ]),
            'packages' => Package::query()->where('is_active', true)->orderBy('display_order')->get(),
            'durations' => PackageDurationOption::query()->where('is_active', true)->orderBy('display_order')->get(),
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
            ->with(['primaryLocation', 'sublocation', 'owner', 'currentAgency.owner', 'currentPackageAssignment.package', 'packageAssignments.package'])
            ->latest('updated_at');
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
        abort_unless($profile->images()->whereIn('status', ['pending_review', 'approved'])->exists(), 422, 'At least one reviewed image is required.');

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
}
