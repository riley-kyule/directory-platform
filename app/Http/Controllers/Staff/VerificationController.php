<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVerificationCheckRequest;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\VerificationCheck;
use App\Services\ProfileVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(private readonly ProfileVerificationService $verification) {}

    public function index(Request $request): View
    {
        Gate::authorize('verification.view');
        $selectedProfile = $request->filled('profile')
            ? Profile::query()->findOrFail($request->integer('profile'))
            : null;

        return view('staff.verification.index', [
            'profiles' => Profile::query()
                ->select(['id', 'display_name', 'slug', 'status', 'verification_status', 'date_of_birth'])
                ->with('currentAgency:id,name')
                ->orderByRaw("CASE verification_status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 WHEN 'unverified' THEN 2 ELSE 3 END")
                ->orderBy('display_name')
                ->paginate(40),
            'selectedProfile' => $selectedProfile?->load([
                'owner:id,name,email,provider_type', 'currentAgency:id,name',
                'verificationChecks.performer:id,name,email',
            ]),
            'checkTypes' => VerificationCheck::TYPES,
        ]);
    }

    public function store(StoreVerificationCheckRequest $request): RedirectResponse
    {
        $check = DB::transaction(function () use ($request): VerificationCheck {
            $profile = Profile::query()->lockForUpdate()->findOrFail($request->integer('profile_id'));
            $check = VerificationCheck::query()->create([
                'profile_id' => $profile->id,
                'check_type' => $request->validated('check_type'),
                'status' => $request->validated('status'),
                'evidence_reference' => $request->validated('evidence_reference'),
                'notes' => $request->validated('notes'),
                'performed_by' => $request->user()->id,
                'checked_at' => $request->validated('status') === 'pending' ? null : now(),
                'expires_at' => $request->validated('expires_at'),
            ]);
            $previousStatus = $profile->verification_status;
            $newStatus = $this->verification->sync($profile);
            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'verification.record',
                'target_type' => 'profile',
                'target_id' => $profile->id,
                'previous_state' => ['verification_status' => $previousStatus],
                'new_state' => [
                    'verification_status' => $newStatus,
                    'check_id' => $check->id,
                    'check_type' => $check->check_type,
                    'check_status' => $check->status,
                    'expires_at' => $check->expires_at?->toIso8601String(),
                ],
                'reason' => 'Verification evidence record updated by authorized staff.',
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);

            return $check;
        });

        return redirect()->route('staff.verification.index', ['profile' => $check->profile_id])
            ->with('status', 'Verification check recorded.');
    }
}
