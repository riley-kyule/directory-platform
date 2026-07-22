<?php

namespace App\Services;

use App\Models\Profile;

class PublicContactLinks
{
    /** @return array<string, array{label: string, href: string}> */
    public function for(Profile $profile): array
    {
        $contacts = $profile->contacts->where('is_public', true)->keyBy('type');
        $links = [];

        if ($contact = $contacts->get('call')) {
            $links['call'] = ['label' => 'Call', 'href' => 'tel:'.$contact->normalized_value];
        }
        if ($contact = $contacts->get('sms')) {
            $links['sms'] = ['label' => 'SMS', 'href' => 'sms:'.$contact->normalized_value];
        }
        if ($contact = $contacts->get('whatsapp')) {
            $number = preg_replace('/\D/', '', $contact->normalized_value);
            $links['whatsapp'] = ['label' => 'WhatsApp', 'href' => 'https://wa.me/'.$number];
        }
        if ($contact = $contacts->get('telegram_username')) {
            $links['telegram'] = ['label' => 'Telegram', 'href' => 'https://t.me/'.ltrim($contact->normalized_value, '@')];
        } elseif ($contact = $contacts->get('telegram_phone')) {
            $number = preg_replace('/\D/', '', $contact->normalized_value);
            $links['telegram'] = ['label' => 'Telegram', 'href' => 'https://t.me/+'.$number];
        }

        return $links;
    }
}
