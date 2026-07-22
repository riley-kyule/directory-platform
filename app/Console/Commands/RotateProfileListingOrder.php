<?php

namespace App\Console\Commands;

use App\Models\Profile;
use Illuminate\Console\Command;

class RotateProfileListingOrder extends Command
{
    protected $signature = 'profiles:rotate-listing-order';

    protected $description = 'Assign fresh stable random ranks to public profile listings';

    public function handle(): int
    {
        $rotated = 0;

        Profile::query()
            ->publiclyVisible()
            ->select('id')
            ->chunkById(500, function ($profiles) use (&$rotated): void {
                foreach ($profiles as $profile) {
                    $profile->updateQuietly(['listing_rank' => random_int(1, 2_147_483_647)]);
                    $rotated++;
                }
            });

        $this->info("Rotated {$rotated} profile listing rank(s).");

        return self::SUCCESS;
    }
}
