<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Models\ProfileReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && Profile::query()->publiclyVisible()->whereKey($profile->id)->exists();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->user()?->email ?? $this->input('email')]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(ProfileReport::CATEGORIES))],
            'details' => ['required', 'string', 'min:30', 'max:5000'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'website' => ['prohibited'],
        ];
    }
}
