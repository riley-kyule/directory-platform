<?php

namespace App\Services;

use App\Jobs\RotateProfileListingOrderJob;
use App\Models\Profile;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps public listing order fresh without a reliable cron: page loads call
 * triggerIfDue() (see TriggerListingRotation middleware) instead of a
 * scheduled command, since shared cPanel cron has proven unreliable here.
 */
class ListingRotationService
{
    private const LAST_ROTATION_KEY = 'directory-listings:last-rotation';

    private const CLAIM_KEY = 'directory-listings:rotation-claim';

    public function __construct(private readonly DirectorySettings $settings) {}

    public function isDue(): bool
    {
        $lastRotation = Cache::get(self::LAST_ROTATION_KEY);

        // A value that isn't a real DateTimeInterface (missing, or an
        // __PHP_Incomplete_Class from a cache entry the current process can't
        // unserialize) is treated as "due" rather than fatal — this runs on
        // every public page load now, so it must never throw.
        if (! $lastRotation instanceof DateTimeInterface) {
            return true;
        }

        return now()->diffInHours($lastRotation, absolute: true) >= $this->settings->integer('listings.rotation_hours');
    }

    /**
     * Dispatch a rotation job if one is due. Cache::add() is atomic, so a
     * burst of concurrent page loads all seeing "due" still only dispatches
     * one job — the rest lose the race and no-op.
     */
    public function triggerIfDue(): void
    {
        if ($this->isDue() && Cache::add(self::CLAIM_KEY, true, now()->addMinutes(5))) {
            RotateProfileListingOrderJob::dispatch();
        }
    }

    /** @return int Number of profiles whose listing rank was reassigned. */
    public function rotate(): int
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

        Cache::forever(self::LAST_ROTATION_KEY, now());
        Cache::forget(self::CLAIM_KEY);

        return $rotated;
    }
}
