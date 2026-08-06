<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');

        $rules = [
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];

        // System roles (admin/csr/seo/subscriber) keep their seeded name —
        // only their granted permissions are editable through this form.
        if (! ($role instanceof Role && $role->is_system)) {
            $rules['name'] = ['required', 'string', 'max:60'];
        }

        return $rules;
    }
}
