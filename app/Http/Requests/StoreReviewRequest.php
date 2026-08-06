<?php

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'reviewer_name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'website' => ['prohibited'],
        ];
    }
}
