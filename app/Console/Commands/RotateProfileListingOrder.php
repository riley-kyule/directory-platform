<?php

namespace App\Console\Commands;

use App\Services\ListingRotationService;
use Illuminate\Console\Command;

/**
 * Manual/ops entry point. Routine rotation is triggered by public page loads
 * (see TriggerListingRotation middleware + ListingRotationService), not a
 * schedule — shared cPanel cron has proven unreliable here, and a directory
 * with no traffic doesn't need its listing order refreshed anyway.
 */
class RotateProfileListingOrder extends Command
{
    protected $signature = 'profiles:rotate-listing-order {--scheduled : Respect the configured rotation interval}';

    protected $description = 'Assign fresh stable random ranks to public profile listings';

    public function handle(ListingRotationService $rotation): int
    {
        if ($this->option('scheduled') && ! $rotation->isDue()) {
            $this->info('Listing rotation is not due yet.');

            return self::SUCCESS;
        }

        $rotated = $rotation->rotate();
        $this->info("Rotated {$rotated} profile listing rank(s).");

        return self::SUCCESS;
    }
}
