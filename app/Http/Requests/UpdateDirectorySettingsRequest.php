<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDirectorySettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'privileged_mfa_enforced' => $this->boolean('privileged_mfa_enforced'),
            'age_gate_enabled' => $this->boolean('age_gate_enabled'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'platform_name' => ['nullable', 'string', 'max:80'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'google_site_verification' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'bing_site_verification' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'age_gate_enabled' => ['required', 'boolean'],
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
            'processing_memory_limit_mb' => ['required', 'integer', 'between:128,4096'],
            'video_max_megabytes' => ['required', 'integer', 'between:1,2048'],
            'video_max_duration_seconds' => ['required', 'integer', 'between:5,1800'],
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
