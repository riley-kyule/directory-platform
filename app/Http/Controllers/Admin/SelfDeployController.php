<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunSelfDeploy;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Services\SelfDeployService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SelfDeployController extends Controller
{
    public function __construct(private readonly SelfDeployService $selfDeploy) {}

    public function check(): RedirectResponse
    {
        Gate::authorize('settings.manage');
        abort_unless($this->selfDeploy->enabled(), 404);

        $remote = $this->selfDeploy->remoteCommit();
        if ($remote === null) {
            return back()->withErrors(['deployment' => 'Could not reach the repository to check for updates.']);
        }

        return back()->with('deployment_check', [
            'remote_commit' => $remote,
        ]);
    }

    public function deploy(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        abort_unless($this->selfDeploy->enabled(), 404);

        $deployment = Deployment::query()->create([
            'status' => 'queued',
            'triggered_by' => $request->user()->id,
        ]);

        RunSelfDeploy::dispatch($deployment->id);

        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'deployment.trigger',
            'target_type' => 'deployment',
            'target_id' => $deployment->id,
            'previous_state' => [],
            'new_state' => ['status' => 'queued'],
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(500)->toString(),
        ]);

        return back()->with('status', 'Deploy queued — this runs in the background and can take a few minutes. Refresh this page to check progress.');
    }
}
