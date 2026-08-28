<?php

namespace App\Console\Commands;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Services\ProfileImageVisibility;
use Illuminate\Console\Command;

/**
 * One-shot backfill for the "uploads publish immediately" change: media
 * processed before it shipped can be sitting in pending_review on a profile
 * that is already live. Move every such photo/video to approved. Safe to run
 * repeatedly. (Unpublishing on deactivation/ban is already handled by the
 * lifecycle controllers, so this only publishes.)
 */
class ReconcileMediaVisibility extends Command
{
    protected $signature = 'media:reconcile-visibility';

    protected $description = 'Publish processed media that is stuck in pending_review on a live profile';

    public function handle(ProfileImageVisibility $visibility): int
    {
        $published = 0;

        Profile::query()
            ->where('status', ProfileStatus::Active)
            ->where(fn ($q) => $q->whereHas('images', fn ($i) => $i->where('status', 'pending_review'))
                ->orWhereHas('videos', fn ($v) => $v->where('status', 'pending_review')))
            ->with(['images', 'videos'])
            ->chunkById(100, function ($profiles) use ($visibility, &$published): void {
                foreach ($profiles as $profile) {
                    $published += $profile->images->where('status', 'pending_review')->count()
                        + $profile->videos->where('status', 'pending_review')->count();
                    $visibility->publish($profile);
                }
            });

        $this->info("Published {$published} stuck media item(s).");

        return self::SUCCESS;
    }
}
