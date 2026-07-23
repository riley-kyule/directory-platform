<?php

namespace App\Http\Requests;

use App\Models\DirectoryRedirect;
use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileSlugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('seo.slugs') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => str($this->input('slug'))->slug()->toString()]);
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('profiles', 'slug')->ignore($this->route('profile')),
                Rule::unique('profile_slug_histories', 'old_slug'),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profile = $this->route('profile');
            if ($profile instanceof Profile && $profile->slug === $this->input('slug')) {
                $validator->errors()->add('slug', 'Enter a different slug.');
            }
            if (DirectoryRedirect::query()->where('source_path', '/escort/'.$this->input('slug'))->exists()) {
                $validator->errors()->add('slug', 'That URL is reserved by redirect history.');
            }
        }];
    }
}
