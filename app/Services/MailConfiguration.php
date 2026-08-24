<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Config;
use Throwable;

class MailConfiguration
{
    public function apply(?MailSetting $settings = null): ?MailSetting
    {
        try {
            $settings ??= MailSetting::query()->first();
            if (! $settings) {
                return null;
            }

            Config::set('mail.default', $settings->mailer);
            Config::set('mail.from.address', $settings->from_address);
            Config::set('mail.from.name', $settings->from_name);
            Config::set('mail.mailers.sendmail.path', $settings->sendmail_path);

            if ($settings->mailer === 'smtp') {
                Config::set('mail.mailers.smtp.scheme', $settings->smtp_scheme ?: null);
                Config::set('mail.mailers.smtp.host', $settings->smtp_host);
                Config::set('mail.mailers.smtp.port', $settings->smtp_port);
                Config::set('mail.mailers.smtp.username', $settings->smtp_username);
                Config::set('mail.mailers.smtp.password', $settings->smtp_password);
            }

            return $settings;
        } catch (Throwable) {
            // The table may not exist during first-run migrations, or an old
            // APP_KEY may be unable to decrypt a stored SMTP password.
            return null;
        }
    }
}
