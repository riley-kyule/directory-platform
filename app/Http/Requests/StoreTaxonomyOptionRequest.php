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
            'is_active' => $this->boolean('is_active'),
            'requires_bust_size' => $this->boolean('requires_bust_size'),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                'gender', 'ethnicity', 'bust_size', 'build', 'language', 'service', 'rate_period',
            ])],
            'label' => ['required', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['boolean'],
            'requires_bust_size' => ['boolean'],
        ];
    }
}
