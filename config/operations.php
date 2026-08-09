<?php

return [
    'error_tracking' => [
        'enabled' => (bool) env('ERROR_TRACKING_ENABLED', false),
        'send_default_pii' => false,
    ],
    'health_rate_limit' => (int) env('HEALTH_RATE_LIMIT', 120),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'artifact_retention_days' => (int) env('ARTIFACT_RETENTION_DAYS', 90),
    'scheduler_stale_seconds' => (int) env('SCHEDULER_STALE_SECONDS', 180),
    'slo' => [
        'window_minutes' => (int) env('SLO_WINDOW_MINUTES', 43200),
        'http_availability_target' => (float) env('SLO_HTTP_AVAILABILITY_TARGET', 99.9),
    ],
];
