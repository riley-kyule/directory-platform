<?php

namespace App\Http\Requests;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Services\PolicyAcceptanceService;
use App\Services\ProfileMediaAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'policy_acceptances' => ['nullable', 'array'],
            'policy_acceptances.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profile = $this->route('profile');
            if ($profile instanceof Profile && ! app(PolicyAcceptanceService::class)->allRequiredSelected(
                'renewal_request',
                $this->input('policy_acceptances', []),
                $this->user(),
                $profile,
            )) {
                $validator->errors()->add('policy_acceptances', 'Accept every required provider policy before requesting renewal.');
            }
        }];
    }
}
