<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Same listing fields/validation as ProfileOnboardingRequest (location,
 * taxonomy, availability, services, package, etc. — including its after()
 * conditional checks, inherited unchanged), plus who the resulting listing
 * belongs to, since staff is creating this for someone else rather than
 * for themselves.
 */
class StaffCreateProfileRequest extends ProfileOnboardingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('profiles.create') ?? false;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'owner_mode' => ['required', Rule::in(['existing_user', 'new_user', 'agency'])],
            'existing_user_id' => [
                'required_if:owner_mode,existing_user',
                'nullable',
                Rule::exists('users', 'id')->where('account_type', AccountType::Provider->value),
            ],
            'new_user_name' => ['required_if:owner_mode,new_user', 'nullable', 'string', 'max:255'],
            'new_user_email' => ['required_if:owner_mode,new_user', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'new_user_password' => ['required_if:owner_mode,new_user', 'nullable', Password::defaults()],
            'agency_id' => ['required_if:owner_mode,agency', 'nullable', Rule::exists('agencies', 'id')],
        ]);
    }
}
