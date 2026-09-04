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
    protected $signature = 'system:launch-check
        {--production : Enforce production-only configuration requirements}
        {--allow-cold-start : Do not block on scheduler-heartbeat/backup freshness. Only for a first-ever deploy, before cron or any backup has had a chance to run yet — every later run should omit this so a real regression still fails the check.}';

    protected $description = 'Validate critical application, security, operations, policy, and recovery launch requirements';

    public function handle(): int
    {
        $production = (bool) $this->option('production');
        $coldStart = (bool) $this->option('allow-cold-start');
        $mfaEnforced = app(DirectorySettings::class)->boolean('security.privileged_mfa_enforced');
        $settings = app(DirectorySettings::class);

        $failed = $this->runChecks([
            ['Application key configured', filled(config('app.key'))],
            ['Database responds', $this->databaseResponds()],
            ['Storage directory is writable', is_writable(storage_path())],
            ['GD image processing is available for media and PWA icons', function_exists('imagepng') && function_exists('imagecreatefromstring')],
            ['Privileged MFA enrollment complete when enabled', ! $mfaEnforced || ! User::query()->whereHas('roles', fn ($query) => $query->whereIn('slug', ['admin', 'csr', 'seo']))->whereNull('two_factor_confirmed_at')->exists()],
            ['All policy types published', PolicyVersion::query()->published()->distinct()->count('policy_type') === count(PolicyVersion::TYPES)],
        ]);

        $failed = $this->runChecks([
            ['Scheduler heartbeat is fresh', SystemHeartbeat::query()->where('name', 'scheduler')->where('last_seen_at', '>=', now()->subMinutes(config('operations.scheduler_stale_minutes')))->exists()],
            ['Queue worker heartbeat is fresh', SystemHeartbeat::query()->where('name', 'queue_worker')->where('last_seen_at', '>=', now()->subMinutes(config('operations.queue_worker_stale_minutes')))->exists()],
            ['Database backup is fresh and verified', BackupRecord::query()->where('backup_type', 'database')->whereNotNull('verified_at')->where('completed_at', '>=', now()->subHours(config('operations.backup_stale_hours')))->exists()],
            ['Media backup is fresh and verified', BackupRecord::query()->where('backup_type', 'media')->whereNotNull('verified_at')->where('completed_at', '>=', now()->subHours(config('operations.backup_stale_hours')))->exists()],
        ], allowFailureWithWarning: $coldStart) || $failed;

        if ($production) {
            $checks = [
                ['APP_ENV is production', app()->environment('production')],
                ['Debug mode is disabled', ! config('app.debug')],
                ['Canonical application URL uses HTTPS', str_starts_with(config('app.url'), 'https://')],
                ['Database is not SQLite', DB::getDriverName() !== 'sqlite'],
                ['Queue connection is asynchronous', config('queue.default') !== 'sync'],
                ['Session storage is shared/persistent', in_array(config('session.driver'), ['database', 'redis'], true)],
                ['Cache storage is shared/persistent', in_array(config('cache.default'), ['database', 'redis', 'memcached', 'dynamodb'], true)],
                ['Notification mailer delivers externally', ! in_array(config('mail.default'), ['log', 'array'], true)],
            ];
            if (config('security.require_google_admin_sso')) {
                $checks[] = ['Google Staff SSO is configured', filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'))];
            }
            $failed = $this->runChecks($checks) || $failed;
        }

        $this->runAdvisories([
            ['Support email is configured', $settings->string('site.support_email') !== ''],
            // Advisory, not blocking: video processing is fail-closed at the job
            // level (an upload visibly fails rather than publishing unsafe media),
            // and local backups still beat none — neither should stop a deploy
            // that is otherwise sound. Harden both before a real public launch.
            ['Video inspection is configured (ffprobe)', $this->executableSetting($settings->string('media.ffprobe_path'))],
            ['Video transcoding is configured (ffmpeg)', $this->executableSetting($settings->string('media.ffmpeg_path'))],
            ['Backups use non-local storage', ! in_array(config('operations.backup_disk'), ['local', 'public'], true)],
            ['Google Search Console ownership tag is configured', $settings->string('seo.google_site_verification') !== ''],
            ['Moderation escalation scan is fresh', SystemHeartbeat::query()->where('name', 'moderation_escalation')->where('last_seen_at', '>=', now()->subMinutes(config('operations.moderation_escalation_stale_minutes')))->exists()],
            ['Privacy-retention cleanup is fresh', SystemHeartbeat::query()->where('name', 'privacy_retention')->where('last_seen_at', '>=', now()->subHours(config('operations.privacy_retention_stale_hours')))->exists()],
            ['Restore drill is current', $this->restoreDrillCurrent()],
            ['No failed queue jobs are waiting', DB::table('failed_jobs')->count() === 0],
        ]);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<int, array{0: string, 1: bool}> $checks */
    private function runAdvisories(array $checks): void
    {
        foreach ($checks as [$label, $passed]) {
            if ($passed) {
                $this->components->info($label);
            } else {
                $this->components->warn($label.' (advisory)');
            }
        }
    }

    /** @param array<int, array{0: string, 1: bool}> $checks */
    private function runChecks(array $checks, bool $allowFailureWithWarning = false): bool
    {
        $failed = false;
        foreach ($checks as [$label, $passed]) {
            if ($passed) {
                $this->components->info($label);
            } elseif ($allowFailureWithWarning) {
                $this->components->warn("{$label} (skipped: --allow-cold-start)");
            } else {
                $this->components->error($label);
                $failed = true;
            }
        }

        return $failed;
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

    private function restoreDrillCurrent(): bool
    {
        $heartbeat = SystemHeartbeat::query()->find('restore_drill');

        return $heartbeat?->last_seen_at?->gte(now()->subDays(config('operations.restore_drill_stale_days')))
            && (int) ($heartbeat->metadata['pending_migrations'] ?? 0) === 0;
    }

    private function executableSetting(string $path): bool
    {
        return $path !== '' && is_file($path) && is_executable($path);
    }
}
