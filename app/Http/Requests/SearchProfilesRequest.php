<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SearchProfilesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $services = array_values(array_filter((array) $this->input('services', [])));
        $this->merge([
            'q' => filled($this->input('q')) ? str($this->input('q'))->squish()->toString() : null,
            'services' => $services,
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'city' => [
                'nullable', 'string', 'max:160',
                Rule::exists('locations', 'slug')->where(fn ($query) => $query->whereNull('parent_id')->where('status', 'published')),
            ],
            'neighbourhood' => [
                'nullable', 'string', 'max:160',
                Rule::exists('locations', 'slug')->where(fn ($query) => $query->whereNotNull('parent_id')->where('status', 'published')),
            ],
            'gender' => $this->taxonomyRule('gender'),
            'ethnicity' => $this->taxonomyRule('ethnicity'),
            'build' => $this->taxonomyRule('build'),
            'bust_size' => $this->taxonomyRule('bust_size'),
            'availability' => ['nullable', Rule::in(['incall', 'outcall', 'both'])],
            'services' => ['array', 'max:10'],
            'services.*' => [
                'string', 'distinct',
                Rule::exists('taxonomy_options', 'slug')->where(fn ($query) => $query->where('type', 'service')->where('is_active', true)),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('city') || ! $this->filled('neighbourhood')) {
                return;
            }

            $validPair = Location::query()
                ->where('slug', $this->input('neighbourhood'))
                ->where('status', 'published')
                ->whereHas('parent', fn ($query) => $query
                    ->where('slug', $this->input('city'))
                    ->where('status', 'published'))
                ->exists();

            if (! $validPair) {
                $validator->errors()->add('neighbourhood', 'The selected neighbourhood does not belong to that city.');
            }
        }];
    }

    /** @return array<int, mixed> */
    private function taxonomyRule(string $type): array
    {
        return [
            'nullable', 'string', 'max:160',
            Rule::exists('taxonomy_options', 'slug')->where(fn ($query) => $query->where('type', $type)->where('is_active', true)),
        ];
    }
}
