<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\ProviderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgencyOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->account_type === AccountType::Provider
            && $this->user()?->provider_type === ProviderType::Agency
            && $this->user()?->agency === null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('agencies', 'name')],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
