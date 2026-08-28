<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reviews.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
