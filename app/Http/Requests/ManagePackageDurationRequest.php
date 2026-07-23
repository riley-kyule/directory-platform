<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagePackageDurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('packages.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:60'],
            'duration_days' => [
                'required',
                'integer',
                'between:1,3650',
                Rule::unique('package_duration_options', 'duration_days')
                    ->ignore($this->route('duration')),
            ],
            'display_order' => ['required', 'integer', 'between:0,1000'],
            'is_active' => ['boolean'],
        ];
    }
}
