<?php

return [
    'error_tracking' => [
        'enabled' => (bool) env('ERROR_TRACKING_ENABLED', false),
        'send_default_pii' => false,
    ],
    'health_rate_limit' => (int) env('HEALTH_RATE_LIMIT', 120),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'artifact_retention_days' => (int) env('ARTIFACT_RETENTION_DAYS', 90),
];
