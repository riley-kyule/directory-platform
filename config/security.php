<?php

return [
    'privileged_mfa_enforced' => (bool) env('PRIVILEGED_MFA_ENFORCED', true),
    'privileged_mfa_session_hours' => (int) env('PRIVILEGED_MFA_SESSION_HOURS', 12),
];
