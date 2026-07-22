<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxonomyOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('seo.content') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => $this->filled('country_code') ? strtoupper((string) $this->input('country_code')) : null,
            'is_active' => $this->boolean('is_active'),
            'requires_bust_size' => $this->boolean('requires_bust_size'),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                'gender', 'ethnicity', 'hair_color', 'hair_length', 'bust_size', 'build',
                'sexual_orientation', 'language', 'service', 'rate_period',
            ])],
            'label' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['boolean'],
            'requires_bust_size' => ['boolean'],
        ];
    }
}
