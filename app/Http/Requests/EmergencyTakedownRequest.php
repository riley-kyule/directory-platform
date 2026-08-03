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
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
