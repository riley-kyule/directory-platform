<?php

namespace App\Http\Requests;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Services\ProfileMediaAccess;

class UpdateOwnedProfileRequest extends ProfileOnboardingRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && app(ProfileMediaAccess::class)->owns($this->user(), $profile)
            && in_array($profile->status, [ProfileStatus::Draft, ProfileStatus::Active], true);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['requested_package_id'], $rules['date_of_birth']);

        return $rules;
    }
}
