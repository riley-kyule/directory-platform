<?php

namespace App\Services;

class KnownCrawler
{
    private const USER_AGENT_NEEDLES = [
        'bot', 'crawler', 'spider', 'slurp', 'baiduspider', 'facebookexternalhit',
        'linkedinbot', 'ia_archiver', 'headlesschrome', 'lighthouse', 'pagespeed',
    ];

    public function matches(?string $userAgent): bool
    {
        $userAgent = strtolower((string) $userAgent);
        if ($userAgent === '') {
            return false;
        }

        foreach (self::USER_AGENT_NEEDLES as $needle) {
            if (str_contains($userAgent, $needle)) {
                return true;
            }
        }

        return false;
    }
}
