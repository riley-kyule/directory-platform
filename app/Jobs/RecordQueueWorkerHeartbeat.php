<?php

namespace App\Jobs;

use App\Models\SystemHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordQueueWorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        SystemHeartbeat::query()->updateOrCreate(
            ['name' => 'queue_worker'],
            ['last_seen_at' => now(), 'metadata' => ['host' => gethostname() ?: 'unknown']],
        );
    }
}
