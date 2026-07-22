<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('profiles.activate')
            && $this->user()?->hasPermission('packages.assign');
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'assigned_package_id' => [
                Rule::requiredIf($this->input('decision') === 'approve'),
                'nullable',
                Rule::exists('packages', 'id')->where('is_active', true),
            ],
            'duration_option_id' => [
                Rule::requiredIf($this->input('decision') === 'approve'),
                'nullable',
                Rule::exists('package_duration_options', 'id')->where('is_active', true),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
