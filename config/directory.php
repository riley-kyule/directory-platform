<?php

return [
    'agency_profile_limit' => (int) env('DIRECTORY_AGENCY_PROFILE_LIMIT', 15),
    'new_profile_days' => (int) env('DIRECTORY_NEW_PROFILE_DAYS', 14),
    'sitemap_chunk_size' => (int) env('DIRECTORY_SITEMAP_CHUNK_SIZE', 10000),

    'activity' => [
        'online_minutes' => (int) env('DIRECTORY_ONLINE_MINUTES', 30),
        'recently_active_minutes' => (int) env('DIRECTORY_RECENTLY_ACTIVE_MINUTES', 360),
    ],

    'media' => [
        'maximum_file_kilobytes' => (int) env('DIRECTORY_MEDIA_MAX_KB', 51200),
        'minimum_width' => (int) env('DIRECTORY_MEDIA_MIN_WIDTH', 600),
        'minimum_height' => (int) env('DIRECTORY_MEDIA_MIN_HEIGHT', 600),
        'maximum_dimension' => (int) env('DIRECTORY_MEDIA_MAX_DIMENSION', 12000),
        'maximum_pixels' => (int) env('DIRECTORY_MEDIA_MAX_PIXELS', 40000000),
        'minimum_aspect_ratio' => (float) env('DIRECTORY_MEDIA_MIN_ASPECT_RATIO', 0.4),
        'maximum_aspect_ratio' => (float) env('DIRECTORY_MEDIA_MAX_ASPECT_RATIO', 2.5),
        'accepted_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'webp_quality' => (int) env('DIRECTORY_WEBP_QUALITY', 82),
        'derivative_widths' => [320, 640, 960, 1280],
    ],
];
