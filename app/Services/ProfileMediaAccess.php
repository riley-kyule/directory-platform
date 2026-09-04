<?php

namespace App\Services;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Models\User;

class ProfileMediaAccess
{
    public function canView(User $user, Profile $profile): bool
    {
        return $user->hasPermission('profiles.view-private')
            || $user->hasPermission('media.review')
            || $this->owns($user, $profile);
    }

    public function canUpload(User $user, Profile $profile): bool
    {
        if ($user->hasPermission('media.upload')) {
            return true;
        }

        return $this->owns($user, $profile)
            && in_array($profile->status, [ProfileStatus::Draft, ProfileStatus::Active], true);
    }

    public function canRemove(User $user, Profile $profile): bool
    {
        if ($user->hasPermission('media.remove')) {
            return true;
        }

        return $this->owns($user, $profile)
            && in_array($profile->status, [ProfileStatus::Draft, ProfileStatus::Active], true);
    }

    /** Backwards-compatible UI capability: upload and removal are enforced separately at endpoints. */
    public function canManage(User $user, Profile $profile): bool
    {
        return $this->canUpload($user, $profile) || $this->canRemove($user, $profile);
    }

    public function owns(User $user, Profile $profile): bool
    {
        if ($profile->owner_user_id === $user->id) {
            return true;
        }

        return $user->agency?->profiles()
            ->whereKey($profile->id)
            ->wherePivotNull('unassigned_at')
            ->exists() ?? false;
    }
}
