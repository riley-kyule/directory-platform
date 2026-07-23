<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewModerationAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('moderation.appeals') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'resolution' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
