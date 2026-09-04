<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'quarantine' => [
            'driver' => 'local',
            'root' => storage_path('app/private/quarantine'),
            'serve' => false,
            'throw' => true,
        ],

        // A directory here can be renamed straight into public/media/profiles,
        // so it must be created world-traversable regardless of the worker's
        // umask — otherwise the web server 404s every file inside it.
        'media_staging' => [
            'driver' => 'local',
            'root' => storage_path('app/media-staging'),
            'serve' => false,
            'throw' => true,
            'permissions' => [
                'file' => ['public' => 0o644, 'private' => 0o644],
                'dir' => ['public' => 0o755, 'private' => 0o755],
            ],
        ],

        'media_review' => [
            'driver' => 'local',
            'root' => storage_path('app/private/media-review'),
            'serve' => false,
            'throw' => true,
        ],

        // Host-relative URLs: these files are served from public/ on whatever
        // host the request arrived on, so a wrong or www/apex-mismatched
        // APP_URL can't point the logo, favicon or profile images at a dead
        // origin. Callers that need an absolute URL (og:image) wrap these in
        // url() themselves.
        'profile_media' => [
            'driver' => 'local',
            'root' => public_path('media/profiles'),
            'url' => '/media/profiles',
            'visibility' => 'public',
            'throw' => true,
            'permissions' => [
                'file' => ['public' => 0o644, 'private' => 0o644],
                'dir' => ['public' => 0o755, 'private' => 0o755],
            ],
        ],

        'branding' => [
            'driver' => 'local',
            'root' => public_path('branding'),
            'url' => '/branding',
            'visibility' => 'public',
            'throw' => true,
            'permissions' => [
                'file' => ['public' => 0o644, 'private' => 0o644],
                'dir' => ['public' => 0o755, 'private' => 0o755],
            ],
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
