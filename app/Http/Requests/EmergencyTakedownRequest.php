<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmergencyTakedownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('moderation.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
