<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Models\VerificationCheck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVerificationCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('verification.manage') ?? false;
    }

    public function rules(): array
    {
        $decision = in_array($this->input('status'), ['verified', 'rejected'], true);

        return [
            'profile_id' => ['required', 'integer', Rule::exists('profiles', 'id')],
            'check_type' => ['required', Rule::in(array_keys(VerificationCheck::TYPES))],
            'status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'evidence_reference' => [Rule::requiredIf($decision), 'nullable', 'string', 'min:3', 'max:1000'],
            'notes' => ['required', 'string', 'min:10', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('check_type') !== 'agency_authorization') {
                return;
            }
            $profile = Profile::query()->find($this->integer('profile_id'));
            if ($profile && ! $profile->currentAgency()->exists()) {
                $validator->errors()->add('check_type', 'Agency authorization applies only to an agency-managed profile.');
            }
        }];
    }
}
