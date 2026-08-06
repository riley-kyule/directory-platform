<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Self-deploy from the admin panel
    |--------------------------------------------------------------------------
    |
    | Lets Admin > Settings check for new commits on the configured branch and
    | trigger deploy/deploy.sh from a background job, so updating a cPanel
    | site doesn't require SSH access. Off by default — every value below
    | must be explicitly configured before the button appears at all.
    |
    | repo_url may embed a token (https://<token>@github.com/...) — keep it in
    | .env only, never commit it, never echo it anywhere in captured output.
    |
    */

    'enabled' => (bool) env('SELF_DEPLOY_ENABLED', false),
    'repo_url' => env('SELF_DEPLOY_REPO_URL'),
    'branch' => env('SELF_DEPLOY_BRANCH', 'main'),

    // The parent directory containing releases/, shared/, and current — NOT
    // base_path(), which is the currently-active release itself. Required so
    // the job knows where to run deploy/deploy.sh with the right
    // DEPLOY_APP_ROOT; there's no reliable way to infer it from within a
    // running request.
    'app_root' => env('SELF_DEPLOY_APP_ROOT'),

    'manage_docroot' => (bool) env('SELF_DEPLOY_MANAGE_DOCROOT', true),
];
