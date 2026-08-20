<?php

namespace App\Console\Commands;

use App\Models\ProfileConversionDaily;
use App\Models\ProfileViewDaily;
use Illuminate\Console\Command;

class PruneProfileConversions extends Command
{
    protected $signature = 'conversion:prune';

    protected $description = 'Delete aggregate profile conversion totals past the configured retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays(config('operations.profile_conversion_retention_days'))->toDateString();
        $conversions = ProfileConversionDaily::query()->where('event_date', '<', $cutoff)->delete();
        $views = ProfileViewDaily::query()->where('event_date', '<', $cutoff)->delete();
        $this->info("Pruned {$conversions} profile conversion and {$views} profile view row(s) older than {$cutoff}.");

        return self::SUCCESS;
    }
}
