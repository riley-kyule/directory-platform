<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDirectorySettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['privileged_mfa_enforced' => $this->boolean('privileged_mfa_enforced')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'privileged_mfa_enforced' => ['required', 'boolean'],
            'agency_profile_limit' => ['required', 'integer', 'between:1,100'],
            'new_profile_days' => ['required', 'integer', 'between:1,365'],
            'listing_rotation_hours' => ['required', 'integer', 'between:1,168'],
            'micro_location_min_profiles' => ['required', 'integer', 'between:2,100'],
            'maximum_file_megabytes' => ['required', 'integer', 'between:1,50'],
            'minimum_width' => ['required', 'integer', 'between:200,5000'],
            'minimum_height' => ['required', 'integer', 'between:200,5000'],
            'maximum_dimension' => ['required', 'integer', 'between:600,20000'],
            'maximum_megapixels' => ['required', 'integer', 'between:1,100'],
            'minimum_aspect_ratio' => ['required', 'numeric', 'between:0.1,5'],
            'maximum_aspect_ratio' => ['required', 'numeric', 'between:0.1,5', 'gt:minimum_aspect_ratio'],
            'webp_quality' => ['required', 'integer', 'between:50,100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->integer('maximum_dimension') < max($this->integer('minimum_width'), $this->integer('minimum_height'))) {
                $validator->errors()->add('maximum_dimension', 'The maximum dimension must be at least as large as both minimum dimensions.');
            }

            if ($this->integer('maximum_megapixels') * 1_000_000 < $this->integer('minimum_width') * $this->integer('minimum_height')) {
                $validator->errors()->add('maximum_megapixels', 'The decoded pixel limit must accommodate the minimum image dimensions.');
            }
        }];
    }
}
