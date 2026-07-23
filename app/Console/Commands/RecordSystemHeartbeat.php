<?php

namespace App\Console\Commands;

use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;

class RecordSystemHeartbeat extends Command
{
    protected $signature = 'system:heartbeat {name=scheduler}';

    protected $description = 'Record an operational heartbeat';

    public function handle(): int
    {
        SystemHeartbeat::query()->updateOrCreate(
            ['name' => $this->argument('name')],
            ['last_seen_at' => now(), 'metadata' => ['host' => gethostname() ?: 'unknown']],
        );
        $this->info("Heartbeat recorded for {$this->argument('name')}.");

        return self::SUCCESS;
    }
}
