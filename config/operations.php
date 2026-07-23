<?php

return [
    'scheduler_stale_minutes' => (int) env('OPS_SCHEDULER_STALE_MINUTES', 5),
    'backup_stale_hours' => (int) env('OPS_BACKUP_STALE_HOURS', 30),
    'queue_age_warning_minutes' => (int) env('OPS_QUEUE_AGE_WARNING_MINUTES', 10),
    'disk_free_warning_megabytes' => (int) env('OPS_DISK_FREE_WARNING_MEGABYTES', 2048),
    'slow_query_milliseconds' => (int) env('OPS_SLOW_QUERY_MILLISECONDS', 500),
    'backup_disk' => env('OPS_BACKUP_DISK', 'local'),
    'backup_directory' => env('OPS_BACKUP_DIRECTORY', 'backups'),
    'backup_retention_days' => (int) env('OPS_BACKUP_RETENTION_DAYS', 14),
];
