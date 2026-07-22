<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('seo.content') ?? false;
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:160'],
            'intro_content' => ['required', 'string', 'min:100', 'max:20000'],
            'bottom_content' => ['nullable', 'string', 'max:50000'],
            'faq_content' => ['nullable', 'string', 'max:10000'],
            'seo_title' => ['required', 'string', 'max:70'],
            'meta_description' => ['required', 'string', 'min:50', 'max:320'],
            'canonical_path' => ['required', 'string', 'max:255', 'regex:/^\/[a-z0-9\/-]+$/'],
        ];
    }
}
