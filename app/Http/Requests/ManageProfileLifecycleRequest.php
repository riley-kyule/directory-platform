<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageProfileLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return match ($this->input('action')) {
            'deactivate' => $user?->hasPermission('profiles.deactivate') ?? false,
            'remove_package' => ($user?->hasPermission('profiles.deactivate') ?? false)
                && ($user?->hasPermission('packages.assign') ?? false),
            'ban' => $user?->hasPermission('profiles.ban') ?? false,
            'renew' => ($user?->hasPermission('profiles.renew') ?? false)
                && ($user?->hasPermission('packages.assign') ?? false),
            'assign_package' => ($user?->canOverrideListingRequirements() ?? false)
                && ($user?->hasPermission('packages.assign') ?? false),
            default => false,
        };
    }

    public function rules(): array
    {
        $assigning = in_array($this->input('action'), ['renew', 'assign_package'], true);

        return [
            'action' => ['required', Rule::in(['deactivate', 'ban', 'renew', 'remove_package', 'assign_package'])],
            'package_id' => [Rule::requiredIf($assigning), 'nullable', Rule::exists('packages', 'id')->where('is_active', true)],
            'duration_option_id' => [Rule::requiredIf($assigning), 'nullable', Rule::exists('package_duration_options', 'id')->where('is_active', true)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'override_requirements' => ['sometimes', 'boolean'],
        ];
    }
}
