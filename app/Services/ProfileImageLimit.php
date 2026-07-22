<?php

namespace App\Services;

use App\Enums\PackageRequestStatus;
use App\Models\Profile;

class ProfileImageLimit
{
    public function for(Profile $profile): int
    {
        $activePackage = $profile->currentPackageAssignment?->package;
        if ($activePackage) {
            return $activePackage->image_limit;
        }

        return $profile->packageRequests()
            ->whereIn('status', [
                PackageRequestStatus::Pending->value,
                PackageRequestStatus::Approved->value,
                PackageRequestStatus::Changed->value,
            ])
            ->latest('id')
            ->first()?->requestedPackage?->image_limit ?? 0;
    }
}
