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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'aliases' => collect(preg_split('/\r\n|\r|\n/', (string) $this->input('aliases', '')))
                ->map(fn (string $line) => trim($line))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        $publishing = $this->input('status') === 'published';
        $location = $this->route('location');
        $canonicalPath = $location ? '/'.$location->full_slug.'-escorts' : null;

        return [
            'status' => ['required', Rule::in(['draft', 'published'])],
            'heading' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:160'],
            'intro_content' => [Rule::requiredIf($publishing), 'nullable', 'string', 'min:100', 'max:20000'],
            'bottom_content' => ['nullable', 'string', 'max:50000'],
            'faq_content' => ['nullable', 'string', 'max:10000'],
            'seo_title' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:70'],
            'meta_description' => [Rule::requiredIf($publishing), 'nullable', 'string', 'min:50', 'max:160'],
            'canonical_path' => [
                Rule::requiredIf($publishing),
                'nullable',
                'string',
                'max:255',
                Rule::in(array_filter([$canonicalPath])),
                Rule::unique('location_contents', 'canonical_path')
                    ->ignore($location?->id, 'location_id'),
            ],
            'aliases' => ['array', 'max:20'],
            'aliases.*' => ['string', 'max:160'],
        ];
    }
}
