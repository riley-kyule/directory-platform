<?php

return [
    'sitemap_chunk_size' => (int) env('DIRECTORY_SITEMAP_CHUNK_SIZE', 10000),

    'activity' => [
        'online_minutes' => (int) env('DIRECTORY_ONLINE_MINUTES', 30),
        'recently_active_minutes' => (int) env('DIRECTORY_RECENTLY_ACTIVE_MINUTES', 360),
    ],

    'media' => [
        'accepted_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'derivative_widths' => [320, 640, 960, 1280],
    ],
];
