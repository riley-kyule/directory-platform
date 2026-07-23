<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageModerationReportRequest;
use App\Http\Requests\ReviewModerationAppealRequest;
use App\Models\AuditLog;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Profile;
use App\Models\ProfileReport;
use App\Services\ModerationEnforcementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModerationController extends Controller
{
    public function __construct(private readonly ModerationEnforcementService $enforcement) {}

    public function index(Request $request): View
    {
        Gate::authorize('moderation.view');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'in_review', 'resolved', 'dismissed'])],
            'priority' => ['nullable', Rule::in(['urgent', 'normal'])],
        ]);

        return view('staff.moderation.index', [
            'reports' => ProfileReport::query()
                ->with(['profile:id,display_name,slug,status', 'assignee:id,name,email'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 ELSE 1 END")
                ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END")
                ->oldest()
                ->paginate(30)
                ->withQueryString(),
            'appeals' => ModerationAppeal::query()
                ->where('status', 'pending')
                ->with(['profile:id,display_name,slug,status', 'appellant:id,name,email', 'moderationAction'])
                ->oldest()
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function show(ProfileReport $report): View
    {
        Gate::authorize('moderation.view');

        return view('staff.moderation.show', [
            'report' => $report->load([
                'profile.primaryLocation', 'profile.sublocation', 'profile.owner',
                'profile.currentAgency.owner', 'reporter', 'assignee', 'actions.actor',
            ]),
        ]);
    }

    public function update(ManageModerationReportRequest $request, ProfileReport $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $report): void {
            $report = ProfileReport::query()->lockForUpdate()->findOrFail($report->id);
            $profile = Profile::query()->lockForUpdate()->findOrFail($report->profile_id);
            $action = $request->validated('action');
            $previousStatus = $profile->status->value;
            $previousReport = $report->only(['status', 'assigned_to', 'resolved_at']);

            match ($action) {
                'assign_to_me', 'start_review' => $report->update([
                    'status' => 'in_review',
                    'assigned_to' => $request->user()->id,
                ]),
                'dismiss' => $report->update(['status' => 'dismissed', 'resolved_at' => now()]),
                'resolve' => $report->update(['status' => 'resolved', 'resolved_at' => now()]),
                'make_private' => $this->enforce($report, $profile, 'make_private'),
                'ban' => $this->enforce($report, $profile, 'ban'),
                'note' => null,
            };

            ModerationAction::query()->create([
                'report_id' => $report->id,
                'profile_id' => $profile->id,
                'actor_user_id' => $request->user()->id,
                'action' => $action,
                'previous_profile_status' => $previousStatus,
                'new_profile_status' => $profile->refresh()->status->value,
                'reason' => $request->validated('reason'),
            ]);
            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'moderation.'.$action,
                'target_type' => 'report',
                'target_id' => $report->id,
                'previous_state' => $previousReport + ['profile_status' => $previousStatus],
                'new_state' => $report->fresh()->only(['status', 'assigned_to', 'resolved_at'])
                    + ['profile_status' => $profile->status->value],
                'reason' => $request->validated('reason'),
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);
        });

        return back()->with('status', 'Moderation case updated.');
    }

    public function reviewAppeal(ReviewModerationAppealRequest $request, ModerationAppeal $appeal): RedirectResponse
    {
        DB::transaction(function () use ($request, $appeal): void {
            $appeal = ModerationAppeal::query()->lockForUpdate()->findOrFail($appeal->id);
            abort_unless($appeal->status === 'pending', 409, 'This appeal has already been reviewed.');
            $profile = Profile::query()->lockForUpdate()->findOrFail($appeal->profile_id);
            $previousStatus = $profile->status->value;
            $approved = $request->validated('decision') === 'approve';

            if ($approved) {
                $this->enforcement->restore($profile);
            }
            $appeal->update([
                'status' => $approved ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id,
                'resolution' => $request->validated('resolution'),
                'resolved_at' => now(),
            ]);
            ModerationAction::query()->create([
                'profile_id' => $profile->id,
                'actor_user_id' => $request->user()->id,
                'action' => $approved ? 'appeal_approved' : 'appeal_rejected',
                'previous_profile_status' => $previousStatus,
                'new_profile_status' => $profile->refresh()->status->value,
                'reason' => $request->validated('resolution'),
            ]);
            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => $approved ? 'moderation.appeal-approved' : 'moderation.appeal-rejected',
                'target_type' => 'moderation-appeal',
                'target_id' => $appeal->id,
                'previous_state' => ['appeal_status' => 'pending', 'profile_status' => $previousStatus],
                'new_state' => ['appeal_status' => $appeal->status, 'profile_status' => $profile->status->value],
                'reason' => $request->validated('resolution'),
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);
        });

        return back()->with('status', 'Appeal decision recorded.');
    }

    private function enforce(ProfileReport $report, Profile $profile, string $action): void
    {
        $action === 'ban'
            ? $this->enforcement->ban($profile)
            : $this->enforcement->makePrivate($profile);
        $report->update([
            'status' => 'resolved',
            'assigned_to' => request()->user()->id,
            'resolved_at' => now(),
        ]);
    }
}
