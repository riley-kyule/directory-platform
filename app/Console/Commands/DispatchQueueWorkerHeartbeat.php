<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueWorkerHeartbeat;
use Illuminate\Console\Command;

class DispatchQueueWorkerHeartbeat extends Command
{
    protected $signature = 'system:dispatch-queue-heartbeat';

    protected $description = 'Dispatch a queue-worker heartbeat probe onto the monitoring queue';

    public function handle(): int
    {
        RecordQueueWorkerHeartbeat::dispatch();
        $this->info('Queue-worker heartbeat probe dispatched.');

        return self::SUCCESS;
    }
}
