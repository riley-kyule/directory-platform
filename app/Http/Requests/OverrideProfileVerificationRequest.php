<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OverrideProfileVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOverrideListingRequirements() ?? false;
    }

    public function rules(): array
    {
        return [
            'profile_id' => ['required', 'integer', 'exists:profiles,id'],
            'reason' => ['required', 'string', 'min:20', 'max:5000'],
            'confirm_override' => ['accepted'],
        ];
    }
}
