<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Models\PolicyVersion;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Services\DirectorySettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LaunchReadinessCheck extends Command
{
    protected $signature = 'system:launch-check {--production : Enforce production-only configuration requirements}';

    protected $description = 'Validate critical application, security, scheduler, policy, and backup launch requirements';

    public function handle(): int
    {
        $production = (bool) $this->option('production');
        $mfaEnforced = app(DirectorySettings::class)->boolean('security.privileged_mfa_enforced');
        $checks = [
            ['Application key configured', filled(config('app.key'))],
            ['Database responds', $this->databaseResponds()],
            ['Storage directory is writable', is_writable(storage_path())],
            ['Privileged MFA enrollment complete when enabled', ! $mfaEnforced || ! User::query()->whereHas('roles', fn ($query) => $query->whereIn('slug', ['admin', 'csr', 'seo']))->whereNull('two_factor_confirmed_at')->exists()],
            ['All policy types published', PolicyVersion::query()->published()->distinct()->count('policy_type') === count(PolicyVersion::TYPES)],
            ['Scheduler heartbeat is fresh', SystemHeartbeat::query()->where('name', 'scheduler')->where('last_seen_at', '>=', now()->subMinutes(config('operations.scheduler_stale_minutes')))->exists()],
            ['Backup is fresh and verified', BackupRecord::query()->whereNotNull('verified_at')->where('completed_at', '>=', now()->subHours(config('operations.backup_stale_hours')))->exists()],
        ];
        if ($production) {
            $checks = [
                ...$checks,
                ['APP_ENV is production', app()->environment('production')],
                ['Debug mode is disabled', ! config('app.debug')],
                ['Canonical application URL uses HTTPS', str_starts_with(config('app.url'), 'https://')],
                ['Database is not SQLite', DB::getDriverName() !== 'sqlite'],
                ['Queue connection is asynchronous', config('queue.default') !== 'sync'],
                ['Session storage is shared/persistent', in_array(config('session.driver'), ['database', 'redis'], true)],
                ['Cache storage is shared/persistent', in_array(config('cache.default'), ['database', 'redis', 'memcached', 'dynamodb'], true)],
            ];
        }

        $failed = false;
        foreach ($checks as [$label, $passed]) {
            $passed ? $this->components->info($label) : $this->components->error($label);
            $failed = $failed || ! $passed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function databaseResponds(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
