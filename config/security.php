<?php

return [
    'privileged_mfa_session_hours' => (int) env('PRIVILEGED_MFA_SESSION_HOURS', 12),

    // Deployments without a Google Workspace tenant can rely on password + MFA
    // for privileged staff accounts instead; set to false to drop the production
    // launch check that otherwise requires Google Staff SSO to be configured.
    'require_google_admin_sso' => (bool) env('LAUNCH_CHECK_REQUIRE_GOOGLE_SSO', true),

    'canonical_host' => env('CANONICAL_HOST', parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: ''),

    // Comma-separated proxy IPs/CIDRs. An empty value trusts no forwarded
    // headers. Never use "*" unless the origin is independently locked down.
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
];
