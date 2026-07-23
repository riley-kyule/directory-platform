<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Services\DirectorySettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RotateProfileListingOrder extends Command
{
    protected $signature = 'profiles:rotate-listing-order {--scheduled : Respect the configured rotation interval}';

    protected $description = 'Assign fresh stable random ranks to public profile listings';

    public function handle(DirectorySettings $settings): int
    {
        $lastRotationKey = 'directory-listings:last-rotation';
        if ($this->option('scheduled')) {
            $lastRotation = Cache::get($lastRotationKey);
            if ($lastRotation && now()->diffInHours($lastRotation, absolute: true) < $settings->integer('listings.rotation_hours')) {
                $this->info('Listing rotation is not due yet.');

                return self::SUCCESS;
            }
        }

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
        Cache::forever($lastRotationKey, now());

        return self::SUCCESS;
    }
}
