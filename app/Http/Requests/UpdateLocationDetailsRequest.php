<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Support\CountryName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLocationDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->hasPermission('seo.locations') ?? false)
            && $this->route('location') instanceof Location;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
        }
    }

    public function rules(): array
    {
        $location = $this->route('location');
        $isTopLevel = $location instanceof Location && $location->parent_id === null;

        return [
            'name' => ['required', 'string', 'max:160'],
            // Child locations inherit the country from their parent — the field
            // is only editable (and only required) on a top-level location.
            'country_code' => $isTopLevel
                ? ['required', 'string', 'size:2']
                : ['nullable'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $code = (string) $this->input('country_code');
            if ($code !== '' && ! CountryName::isValid($code)) {
                $validator->errors()->add('country_code', 'Enter a real ISO country code, e.g. AE for the United Arab Emirates.');
            }
        }];
    }
}
