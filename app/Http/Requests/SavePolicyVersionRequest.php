<?php

namespace App\Http\Requests;

use App\Models\PolicyVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePolicyVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('policies.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['requires_reacceptance' => $this->boolean('requires_reacceptance')]);
    }

    public function rules(): array
    {
        $draft = PolicyVersion::query()
            ->where('policy_type', $this->route('policyType'))
            ->whereNull('published_at')
            ->latest('id')
            ->first();

        return [
            'version' => [
                'required',
                'string',
                'max:40',
                Rule::unique('policy_versions', 'version')
                    ->where('policy_type', $this->route('policyType'))
                    ->ignore($draft?->id),
            ],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'min:100', 'max:100000'],
            'requires_reacceptance' => ['boolean'],
            'action' => ['required', Rule::in(['save_draft', 'publish'])],
        ];
    }
}
