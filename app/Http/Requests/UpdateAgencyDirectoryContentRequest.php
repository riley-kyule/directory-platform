<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyDirectoryContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('seo.content') ?? false;
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:160'],
            'intro_content' => ['required', 'string', 'max:2000'],
            'bottom_content' => ['nullable', 'string', 'max:50000'],
            'seo_title' => ['required', 'string', 'max:70'],
            'meta_description' => ['required', 'string', 'min:50', 'max:320'],
        ];
    }
}
