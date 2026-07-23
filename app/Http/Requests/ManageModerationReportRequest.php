<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageModerationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('moderation.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'assign_to_me', 'start_review', 'note', 'dismiss', 'resolve', 'make_private', 'ban',
            ])],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
