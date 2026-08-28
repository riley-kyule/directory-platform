<?php

namespace App\Services;

use App\Enums\PackageRequestStatus;
use App\Models\Profile;

class ProfileVideoLimit
{
    public function for(Profile $profile): int
    {
        $activePackage = $profile->currentPackageAssignment?->package;
        if ($activePackage) {
            return (int) $activePackage->video_limit;
        }

        return (int) ($profile->packageRequests()
            ->whereIn('status', [
                PackageRequestStatus::Pending->value,
                PackageRequestStatus::Approved->value,
                PackageRequestStatus::Changed->value,
            ])
            ->latest('id')
            ->first()?->requestedPackage?->video_limit ?? 0);
    }
}
