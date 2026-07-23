<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:60'],
            'image_limit' => ['required', 'integer', 'between:1,50'],
            'display_order' => ['required', 'integer', 'between:0,1000'],
            'is_active' => ['boolean'],
        ];
    }
}
