<?php

namespace App\Services;

use App\Models\BackupRecord;
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
            'queue' => $this->queue(),
            'failed_jobs' => $this->failedJobs(),
            'disk' => $this->disk(),
            'backup' => $this->backup(),
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

    private function failedJobs(): array
    {
        try {
            $count = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

            return $this->result($count > 0 ? 'warning' : 'ok', $count > 0 ? 'Failed queue jobs require review.' : 'No failed queue jobs.', $count);
        } catch (Throwable) {
            return $this->result('warning', 'Failed-job count cannot be read.');
        }
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

    /** @return array{status: string, message: string, value?: int|string|null} */
    private function result(string $status, string $message, int|string|null $value = null): array
    {
        return ['status' => $status, 'message' => $message, 'value' => $value];
    }
}
