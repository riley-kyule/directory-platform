<?php

namespace App\Console\Commands;

use App\Models\ProfileConversionDaily;
use Illuminate\Console\Command;

class PruneProfileConversions extends Command
{
    protected $signature = 'conversion:prune';

    protected $description = 'Delete aggregate profile conversion totals past the configured retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays(config('operations.profile_conversion_retention_days'))->toDateString();
        $deleted = ProfileConversionDaily::query()->where('event_date', '<', $cutoff)->delete();
        $this->info("Pruned {$deleted} profile conversion row(s) older than {$cutoff}.");

        return self::SUCCESS;
    }
}
