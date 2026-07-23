<?php

namespace App\Http\Requests;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Services\ProfileMediaAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestProfileRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && app(ProfileMediaAccess::class)->owns($this->user(), $profile)
            && in_array($profile->status, [ProfileStatus::Expired, ProfileStatus::Deactivated], true);
    }

    public function rules(): array
    {
        return [
            'requested_package_id' => [
                'required',
                Rule::exists('packages', 'id')->where('is_active', true),
            ],
        ];
    }
}
