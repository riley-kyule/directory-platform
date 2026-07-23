<?php

namespace App\Services;

use App\Enums\ProviderType;
use App\Models\Profile;

class ProfileVerificationService
{
    /** @return list<string> */
    public function requiredTypes(Profile $profile): array
    {
        $types = ['adult_age', 'identity', 'publishing_rights'];
        $owner = $profile->owner;
        if ($owner?->provider_type === ProviderType::Agency || $profile->currentAgency()->exists()) {
            $types[] = 'agency_authorization';
        }

        return $types;
    }

    public function sync(Profile $profile): string
    {
        $latest = $profile->verificationChecks()
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->unique('check_type')
            ->keyBy('check_type');
        $required = collect($this->requiredTypes($profile));

        $status = match (true) {
            $latest->where('status', 'rejected')->isNotEmpty() => 'rejected',
            $latest->where('status', 'pending')->isNotEmpty() => 'pending',
            $required->every(fn (string $type) => $latest->get($type)?->isCurrentVerified()) => 'verified',
            default => 'unverified',
        };

        $profile->update(['verification_status' => $status]);

        return $status;
    }
}
