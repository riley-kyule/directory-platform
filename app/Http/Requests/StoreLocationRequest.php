<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('seo.locations') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => strtoupper((string) $this->input('country_code')),
            'is_indexable' => $this->boolean('is_indexable'),
        ]);
    }

    public function rules(): array
    {
        $publishing = $this->input('status') === 'published';

        return [
            'parent_id' => ['nullable', Rule::exists('locations', 'id')],
            'country_code' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'type' => ['required', Rule::in(['country', 'county', 'city', 'town', 'district', 'neighbourhood', 'area'])],
            'name' => ['required', 'string', 'max:160'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_indexable' => ['boolean'],
            'intro_content' => [Rule::requiredIf($publishing), 'nullable', 'string', 'min:100', 'max:20000'],
            'faq_content' => ['nullable', 'string', 'max:10000'],
            'seo_title' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:70'],
            'meta_description' => [Rule::requiredIf($publishing), 'nullable', 'string', 'min:50', 'max:320'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('is_indexable') && $this->input('status') !== 'published') {
                $validator->errors()->add('is_indexable', 'Only a published location can be indexable.');
            }

            if ($this->boolean('is_indexable')) {
                $validator->errors()->add('is_indexable', 'A new location needs at least one active profile before indexability can be enabled.');
            }
        }];
    }
}
