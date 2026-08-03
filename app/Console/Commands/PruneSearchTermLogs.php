<?php

namespace App\Console\Commands;

use App\Models\SearchTermLog;
use Illuminate\Console\Command;

class PruneSearchTermLogs extends Command
{
    protected $signature = 'search:prune-term-logs';

    protected $description = 'Delete search term popularity logs past the configured retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays(config('operations.search_term_log_retention_days'))->toDateString();
        $deleted = SearchTermLog::query()->where('search_date', '<', $cutoff)->delete();
        $this->info("Pruned {$deleted} search term log row(s) older than {$cutoff}.");

        return self::SUCCESS;
    }
}
