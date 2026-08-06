<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class SelfDeployService
{
    public function enabled(): bool
    {
        return config('deployment.enabled')
            && filled(config('deployment.repo_url'))
            && filled(config('deployment.app_root'));
    }

    /** The commit this currently-running release was cloned at. */
    public function currentCommit(): ?string
    {
        return $this->runGit(['rev-parse', 'HEAD']);
    }

    /** HEAD of the configured branch on the remote, without cloning anything. */
    public function remoteCommit(): ?string
    {
        $output = $this->runGit(['ls-remote', (string) config('deployment.repo_url'), (string) config('deployment.branch')]);
        if (! $output) {
            return null;
        }

        return trim(strtok($output, "\t\n")) ?: null;
    }

    /** @return array<string, string> */
    public function deployEnv(): array
    {
        return [
            'DEPLOY_APP_ROOT' => (string) config('deployment.app_root'),
            'DEPLOY_REPO_URL' => (string) config('deployment.repo_url'),
            'DEPLOY_BRANCH' => (string) config('deployment.branch'),
            'DEPLOY_MANAGE_DOCROOT' => config('deployment.manage_docroot') ? '1' : '0',
            'PHP_BIN' => PHP_BINARY,
        ];
    }

    /** repo_url may embed a git credential — never let it reach a stored log. */
    public function redact(string $output): string
    {
        return preg_replace('#https://[^/\s@]+@#', 'https://***@', $output) ?? $output;
    }

    /** @param  array<int, string>  $args */
    private function runGit(array $args): ?string
    {
        $process = new Process([...['git'], ...$args], base_path(), null, null, 15);
        $process->run();
        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }
}
