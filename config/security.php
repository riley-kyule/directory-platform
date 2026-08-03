<?php

return [
    'privileged_mfa_session_hours' => (int) env('PRIVILEGED_MFA_SESSION_HOURS', 12),

    // Deployments without a Google Workspace tenant can rely on password + MFA
    // for Admin accounts instead; set to false to drop the production launch
    // check that otherwise requires Google Admin SSO to be configured.
    'require_google_admin_sso' => (bool) env('LAUNCH_CHECK_REQUIRE_GOOGLE_SSO', true),
];
