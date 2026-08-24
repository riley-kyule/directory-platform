<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'mailer' => ['required', Rule::in(['sendmail', 'smtp'])],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:120'],
            'sendmail_path' => ['required_if:mailer,sendmail', 'nullable', Rule::in([
                '/usr/sbin/sendmail -bs -i',
                '/usr/lib/sendmail -bs -i',
            ])],
            'smtp_scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'smtp_host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
