<?php

namespace App\Services;

use App\Enums\ProfileStatus;
use App\Jobs\PublishProfileImages;
use App\Models\Profile;

class ModerationEnforcementService
{
    public function __construct(
        private readonly ProfileImageVisibility $imageVisibility,
        private readonly LocationInventoryService $locationInventory,
    ) {}

    public function makePrivate(Profile $profile): void
    {
        if ($profile->status === ProfileStatus::Active) {
            $this->imageVisibility->unpublish($profile);
        }
        $profile->packageAssignments()->where('status', 'active')->update(['status' => 'moderation_hold']);
        $profile->update(['status' => ProfileStatus::Deactivated]);
        $this->locationInventory->syncForProfile($profile);
    }

    public function ban(Profile $profile): void
    {
        if ($profile->status === ProfileStatus::Active) {
            $this->imageVisibility->unpublish($profile);
        }
        $profile->packageAssignments()->whereIn('status', ['active', 'moderation_hold'])->update(['status' => 'banned']);
        $profile->update(['status' => ProfileStatus::Banned]);
        $this->locationInventory->syncForProfile($profile);
    }

    public function restore(Profile $profile): void
    {
        $assignment = $profile->packageAssignments()
            ->whereIn('status', ['moderation_hold', 'banned'])
            ->where('expires_at', '>', now())
            ->latest('starts_at')
            ->first();
        abort_unless($assignment, 409, 'The profile has no unexpired package to restore. Use the renewal workflow.');

        $profile->packageAssignments()->where('status', 'active')->update(['status' => 'superseded']);
        $assignment->update(['status' => 'active']);
        $profile->update(['status' => ProfileStatus::Active]);
        PublishProfileImages::dispatch($profile->id)->afterCommit();
        $this->locationInventory->syncForProfile($profile);
    }
}
