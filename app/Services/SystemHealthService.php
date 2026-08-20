<?php

namespace App\Services;

use App\Models\BackupRecord;
use App\Models\ModerationAppeal;
use App\Models\ProfileReport;
use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthService
{
    /** @return array<string, array{status: string, message: string, value?: int|string|null}> */
    public function checks(): array
    {
        return [
            'database' => $this->database(),
            'cache' => $this->cache(),
            'scheduler' => $this->scheduler(),
            'queue_worker' => $this->queueWorker(),
            'queue' => $this->queue(),
            'failed_jobs' => $this->failedJobs(),
            'moderation_escalation' => $this->moderationEscalation(),
            'moderation_sla' => $this->moderationSla(),
            'privacy_retention' => $this->privacyRetention(),
            'mail' => $this->mail(),
            'disk' => $this->disk(),
            'backup' => $this->backup(),
            'restore_drill' => $this->restoreDrill(),
        ];
    }

    public function isReady(): bool
    {
        return collect($this->checks())->where('status', 'critical')->isEmpty();
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->result('ok', 'Database connection is available.');
        } catch (Throwable) {
            return $this->result('critical', 'Database connection failed.');
        }
    }

    private function cache(): array
    {
        try {
            $key = 'health:'.str()->uuid();
            Cache::put($key, 'ok', 10);
            $available = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $this->result($available ? 'ok' : 'critical', $available ? 'Cache read/write succeeded.' : 'Cache read/write failed.');
        } catch (Throwable) {
            return $this->result('critical', 'Cache connection failed.');
        }
    }

    private function scheduler(): array
    {
        try {
            $heartbeat = SystemHeartbeat::query()->find('scheduler');
            $fresh = $heartbeat?->last_seen_at?->gte(now()->subMinutes(config('operations.scheduler_stale_minutes')));

            return $this->result($fresh ? 'ok' : 'critical', $fresh ? 'Scheduler heartbeat is current.' : 'Scheduler heartbeat is missing or stale.', $heartbeat?->last_seen_at?->toIso8601String());
        } catch (Throwable) {
            return $this->result('critical', 'Scheduler heartbeat cannot be read.');
        }
    }

    private function queue(): array
    {
        try {
            if (! Schema::hasTable('jobs')) {
                return $this->result('warning', 'Queue table is unavailable.');
            }
            $oldest = DB::table('jobs')->min('available_at');
            $ageMinutes = $oldest ? max(0, (int) floor((now()->timestamp - $oldest) / 60)) : 0;
            $warning = $ageMinutes >= config('operations.queue_age_warning_minutes');

            return $this->result($warning ? 'warning' : 'ok', $warning ? 'The oldest queued job is delayed.' : 'Queue age is within target.', $ageMinutes);
        } catch (Throwable) {
            return $this->result('warning', 'Queue age cannot be read.');
        }
    }

    private function queueWorker(): array
    {
        return $this->heartbeat(
            'queue_worker',
            now()->subMinutes(config('operations.queue_worker_stale_minutes')),
            'Queue worker heartbeat is current.',
            'Queue worker heartbeat is missing or stale.',
        );
    }

    private function failedJobs(): array
    {
        try {
            $count = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

            return $this->result($count > 0 ? 'warning' : 'ok', $count > 0 ? 'Failed queue jobs require review.' : 'No failed queue jobs.', $count);
        } catch (Throwable) {
            return $this->result('warning', 'Failed-job count cannot be read.');
        }
    }

    private function moderationEscalation(): array
    {
        try {
            $heartbeat = SystemHeartbeat::query()->find('moderation_escalation');
            $fresh = $heartbeat?->last_seen_at?->gte(now()->subMinutes(config('operations.moderation_escalation_stale_minutes')));
            $runStatus = $heartbeat?->metadata['status'] ?? null;
            $healthy = $fresh && in_array($runStatus, ['ok', 'delivered'], true);

            return $this->result(
                $healthy ? 'ok' : 'warning',
                match (true) {
                    ! $fresh => 'Moderation escalation scan is missing or stale.',
                    $runStatus === 'blocked' => 'Overdue cases could not be escalated because no active recipient exists.',
                    $runStatus === 'failed' => 'The latest moderation escalation notification failed.',
                    default => 'Moderation escalation scan is current.',
                },
                $heartbeat?->last_seen_at?->toIso8601String(),
            );
        } catch (Throwable) {
            return $this->result('warning', 'Moderation escalation scan cannot be read.');
        }
    }

    private function moderationSla(): array
    {
        try {
            $reports = ProfileReport::query()->overdue()->count();
            $appeals = ModerationAppeal::query()->overdue()->count();
            $total = $reports + $appeals;

            return $this->result(
                $total > 0 ? 'warning' : 'ok',
                $total > 0 ? 'Moderation cases have exceeded response targets.' : 'No moderation cases are overdue.',
                $total,
            );
        } catch (Throwable) {
            return $this->result('warning', 'Moderation SLA status cannot be read.');
        }
    }

    private function privacyRetention(): array
    {
        return $this->heartbeat(
            'privacy_retention',
            now()->subHours(config('operations.privacy_retention_stale_hours')),
            'Privacy-retention cleanup is current.',
            'Privacy-retention cleanup is missing or stale.',
        );
    }

    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        $placeholder = in_array($mailer, ['log', 'array'], true);
        $warning = app()->environment('production') && $placeholder;

        return $this->result(
            $warning ? 'warning' : 'ok',
            $warning ? 'Production notifications are using a non-delivery mailer.' : "Notification mailer is configured as {$mailer}.",
            $mailer,
        );
    }

    private function disk(): array
    {
        $bytes = @disk_free_space(storage_path());
        if ($bytes === false) {
            return $this->result('warning', 'Free disk space cannot be measured.');
        }
        $megabytes = (int) floor($bytes / 1024 / 1024);
        $warning = $megabytes < config('operations.disk_free_warning_megabytes');

        return $this->result($warning ? 'warning' : 'ok', $warning ? 'Free disk space is below target.' : 'Free disk space is within target.', $megabytes);
    }

    private function backup(): array
    {
        try {
            $backup = BackupRecord::query()->where('status', 'completed')->latest('completed_at')->first();
            if (! $backup) {
                return $this->result('warning', 'No completed backup has been recorded.');
            }
            $exists = Storage::disk($backup->disk)->exists($backup->path);
            $fresh = $backup->completed_at->gte(now()->subHours(config('operations.backup_stale_hours')));
            $ok = $exists && $fresh;

            return $this->result($ok ? 'ok' : 'warning', $ok ? 'Latest backup is present and fresh.' : 'Latest backup is missing or stale.', $backup->completed_at->toIso8601String());
        } catch (Throwable) {
            return $this->result('warning', 'Backup freshness cannot be read.');
        }
    }

    private function restoreDrill(): array
    {
        try {
            $heartbeat = SystemHeartbeat::query()->find('restore_drill');
            if (! $heartbeat) {
                return $this->result('warning', 'No restore drill has been recorded yet.');
            }
            $fresh = $heartbeat->last_seen_at->gte(now()->subDays(config('operations.restore_drill_stale_days')));
            $pending = (int) ($heartbeat->metadata['pending_migrations'] ?? 0);
            $ok = $fresh && $pending === 0;

            return $this->result(
                $ok ? 'ok' : 'warning',
                match (true) {
                    ! $fresh => 'Last restore drill is stale.',
                    $pending > 0 => 'Last restore drill left pending migrations against the restored schema.',
                    default => 'Last restore drill succeeded and is current.',
                },
                $heartbeat->last_seen_at->toIso8601String(),
            );
        } catch (Throwable) {
            return $this->result('warning', 'Restore drill freshness cannot be read.');
        }
    }

    private function heartbeat(string $name, \DateTimeInterface $cutoff, string $healthyMessage, string $staleMessage): array
    {
        try {
            $heartbeat = SystemHeartbeat::query()->find($name);
            $fresh = $heartbeat?->last_seen_at?->gte($cutoff);

            return $this->result(
                $fresh ? 'ok' : 'warning',
                $fresh ? $healthyMessage : $staleMessage,
                $heartbeat?->last_seen_at?->toIso8601String(),
            );
        } catch (Throwable) {
            return $this->result('warning', $staleMessage);
        }
    }

    /** @return array{status: string, message: string, value?: int|string|null} */
    private function result(string $status, string $message, int|string|null $value = null): array
    {
        return ['status' => $status, 'message' => $message, 'value' => $value];
    }
}
