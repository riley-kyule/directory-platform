<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('seo.content') ?? false;
    }

    public function rules(): array
    {
        $publishing = $this->input('status') === 'published';

        return [
            'status' => ['required', Rule::in(['draft', 'published'])],
            'heading' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:160'],
            'intro_content' => [Rule::requiredIf($publishing), 'nullable', 'string', 'min:100', 'max:20000'],
            'bottom_content' => ['nullable', 'string', 'max:50000'],
            'faq_content' => ['nullable', 'string', 'max:10000'],
            'seo_title' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:70'],
            'meta_description' => [Rule::requiredIf($publishing), 'nullable', 'string', 'min:50', 'max:320'],
            'canonical_path' => [
                Rule::requiredIf($publishing),
                'nullable',
                'string',
                'max:255',
                'regex:/^\/[a-z0-9\/-]+$/',
                Rule::unique('location_contents', 'canonical_path')
                    ->ignore($this->route('location')?->id, 'location_id'),
            ],
        ];
    }
}
