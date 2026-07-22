<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Services\ProfileImageVisibility;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishProfileImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public readonly int $profileId)
    {
        $this->onQueue('media');
    }

    public function handle(ProfileImageVisibility $visibility): void
    {
        $profile = Profile::query()->findOrFail($this->profileId);
        if (! $profile->status->isPublic()) {
            return;
        }

        $visibility->publish($profile);
    }
}
