<?php

namespace App\Services;

use App\Enums\ProviderType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Str;

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

    /** @return list<string> */
    public function missingTypes(Profile $profile): array
    {
        $latest = $profile->verificationChecks()
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->unique('check_type')
            ->keyBy('check_type');

        return collect($this->requiredTypes($profile))
            ->reject(fn (string $type) => $latest->get($type)?->isCurrentVerified())
            ->values()
            ->all();
    }

    /** @return list<int> */
    public function override(Profile $profile, User $actor, string $reason): array
    {
        abort_unless($actor->canOverrideListingRequirements(), 403, 'Only an Admin or CSR may override verification requirements.');

        $checkIds = [];
        $reference = 'STAFF-OVERRIDE-'.Str::upper(Str::random(16));
        foreach ($this->missingTypes($profile) as $type) {
            $checkIds[] = $profile->verificationChecks()->create([
                'check_type' => $type,
                'status' => 'verified',
                'is_override' => true,
                'evidence_reference' => $reference,
                'notes' => $reason,
                'performed_by' => $actor->id,
                'checked_at' => now(),
                'expires_at' => null,
            ])->id;
        }

        $this->sync($profile);

        return $checkIds;
    }
}
