<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\SelfDeployService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class RunSelfDeploy implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Overrides the cron worker's --timeout=45 flag for this job specifically
    // — deploy.sh (composer install, migrations, launch-check) routinely
    // takes several minutes.
    public int $timeout = 900;

    public function __construct(public readonly int $deploymentId) {}

    public function handle(SelfDeployService $service): void
    {
        $deployment = Deployment::find($this->deploymentId);
        if (! $deployment) {
            return;
        }

        $deployment->update([
            'status' => 'running',
            'from_commit' => $service->currentCommit(),
            'started_at' => now(),
        ]);

        $output = '';
        $successful = false;

        try {
            $process = new Process(
                ['bash', base_path('deploy/deploy.sh')],
                base_path(),
                $service->deployEnv(),
                null,
                $this->timeout,
            );
            $process->run(function (string $type, string $buffer) use (&$output): void {
                $output .= $buffer;
            });
            $successful = $process->isSuccessful();
        } catch (Throwable $e) {
            $output .= "\n\n[deploy job error] {$e->getMessage()}";
            Log::error('Self-deploy job failed', [
                'deployment_id' => $this->deploymentId,
                'error' => $e->getMessage(),
            ]);
        }

        // deploy.sh only reaches this marker once the new release is fully
        // activated; base_path() inside this process still resolves to the
        // OLD release (current moved after this process started), so it
        // can't be used to read the post-deploy commit directly.
        $toCommit = null;
        if (preg_match('/^SELF_DEPLOY_COMMIT=([0-9a-f]{7,40})$/m', $output, $matches)) {
            $toCommit = $matches[1];
        }

        $deployment->update([
            'status' => $successful ? 'succeeded' : 'failed',
            'to_commit' => $toCommit,
            'output' => $service->redact($output),
            'finished_at' => now(),
        ]);
    }
}
