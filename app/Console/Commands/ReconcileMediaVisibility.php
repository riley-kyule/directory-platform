<?php

namespace App\Console\Commands;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Services\ProfileImageVisibility;
use Illuminate\Console\Command;

/**
 * Repair processed media that was not published after a transient storage or
 * queue failure.
 */
class ReconcileMediaVisibility extends Command
{
    protected $signature = 'media:reconcile-visibility';

    protected $description = 'Publish processed media that is still private on live profiles';

    public function handle(ProfileImageVisibility $visibility): int
    {
        $pending = 0;

        Profile::query()
            ->where('status', ProfileStatus::Active)
            ->where(fn ($q) => $q->whereHas('images', fn ($i) => $i->whereIn('status', ['pending_review', 'reviewed']))
                ->orWhereHas('videos', fn ($v) => $v->whereIn('status', ['pending_review', 'reviewed'])))
            ->with(['images', 'videos'])
            ->chunkById(100, function ($profiles) use (&$pending, $visibility): void {
                foreach ($profiles as $profile) {
                    $pending += $profile->images->whereIn('status', ['pending_review', 'reviewed'])->count()
                        + $profile->videos->whereIn('status', ['pending_review', 'reviewed'])->count();
                    $visibility->publish($profile);
                }
            });

        $this->info("Published {$pending} processed media item(s).");

        return self::SUCCESS;
    }
}
