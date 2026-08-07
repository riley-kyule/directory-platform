<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AccountType;
use App\Enums\ProviderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffCreateProfileRequest;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Services\DirectorySettings;
use App\Services\ProfileCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileCreationController extends Controller
{
    public function __construct(
        private readonly ProfileCreationService $profileCreation,
        private readonly DirectorySettings $settings,
    ) {}

    public function create(Request $request): View
    {
        Gate::authorize('profiles.create');

        $ownerSearch = trim((string) $request->query('owner_q', ''));

        return view('staff.directory.create', array_merge($this->profileCreation->formOptions(), [
            'ownerSearch' => $ownerSearch,
            'existingProviders' => $ownerSearch === '' ? collect() : User::query()
                ->where('account_type', AccountType::Provider)
                ->where(fn ($query) => $query->where('name', 'like', "%{$ownerSearch}%")->orWhere('email', 'like', "%{$ownerSearch}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(),
            'agencies' => Agency::query()->orderBy('name')->get(),
        ]));
    }

    public function store(StaffCreateProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $agency = null;
        $ownerUserId = null;

        if ($validated['owner_mode'] === 'existing_user') {
            $ownerUserId = (int) $validated['existing_user_id'];
        } elseif ($validated['owner_mode'] === 'new_user') {
            $owner = User::query()->create([
                'name' => $validated['new_user_name'],
                'email' => $validated['new_user_email'],
                'password' => Hash::make($validated['new_user_password']),
                'status' => 'active',
                'account_type' => AccountType::Provider,
                'provider_type' => ProviderType::Independent,
            ]);
            $owner->forceFill(['email_verified_at' => now()])->save();
            $ownerUserId = $owner->id;
        } else {
            $agency = Agency::query()->findOrFail($validated['agency_id']);
            if ($agency->profiles()->wherePivotNull('unassigned_at')->count() >= $this->settings->integer('profiles.agency_limit')) {
                return back()->withInput()->withErrors(['agency_id' => 'This agency has reached its profile limit.']);
            }
        }

        $profile = DB::transaction(function () use ($validated, $ownerUserId, $agency, $request): Profile {
            $profile = $this->profileCreation->create($validated, $ownerUserId, $agency, requestedByUserId: $request->user()->id);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'profiles.staff-create',
                'target_type' => 'profile',
                'target_id' => $profile->id,
                'previous_state' => [],
                'new_state' => [
                    'display_name' => $profile->display_name,
                    'owner_mode' => $validated['owner_mode'],
                    'owner_user_id' => $profile->owner_user_id,
                    'agency_id' => $agency?->id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);

            return $profile;
        });

        return redirect()->route('staff.directory.show', $profile)
            ->with('status', "{$profile->display_name} created as a draft. Upload media and submit it for review below.");
    }
}
