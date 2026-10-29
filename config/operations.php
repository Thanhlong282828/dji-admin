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
    'metrics' => [
        'ttl_seconds' => (int) env('OBSERVABILITY_METRICS_TTL_SECONDS', 3600),
    ],
    'vps_monitoring' => [
        'enabled' => (bool) env('VPS_MONITORING_ENABLED', false),
        'backend' => env('VPS_MONITORING_BACKEND'),
    ],
    'horizon_expected' => (bool) env('HORIZON_ENABLED', false),
    'alert_delivery' => [
        'channel' => env('OPERATIONS_ALERT_CHANNEL'),
    ],
    'cloudflare_ddos' => [
        'enabled' => (bool) env('CLOUDFLARE_DDOS_MONITOR_ENABLED', true),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'window_minutes' => (int) env('CLOUDFLARE_DDOS_WINDOW_MINUTES', 5),
        'event_threshold' => (int) env('CLOUDFLARE_DDOS_EVENT_THRESHOLD', 100),
        'timeout_seconds' => (int) env('CLOUDFLARE_DDOS_TIMEOUT', 10),
        'under_attack_auto_enable' => (bool) env('CLOUDFLARE_UNDER_ATTACK_AUTO_ENABLE', true),
        'cache_seconds' => 60,
    ],
    'alerts' => [
        'cloudflare_ddos_detected' => ['component' => 'edge-security', 'signal' => 'cloudflare_ddos_events', 'operator' => 'gte', 'threshold' => (int) env('CLOUDFLARE_DDOS_EVENT_THRESHOLD', 100), 'severity' => 'critical', 'runbook' => 'docs/runbooks/ddos-detected.md', 'window_minutes' => (int) env('CLOUDFLARE_DDOS_WINDOW_MINUTES', 5)],
        'high_5xx_rate' => ['component' => 'http', 'signal' => 'http_5xx_rate_percent', 'operator' => 'gte', 'threshold' => (float) env('ALERT_HTTP_5XX_PERCENT', 5), 'severity' => 'critical', 'runbook' => 'docs/runbooks/deployment-failure.md', 'window_minutes' => (int) env('ALERT_HTTP_WINDOW_MINUTES', 5)],
        'high_p95_latency' => ['component' => 'http', 'signal' => 'http_p95_ms', 'operator' => 'gte', 'threshold' => (int) env('ALERT_HTTP_P95_MS', 1500), 'severity' => 'warning', 'runbook' => 'docs/runbooks/deployment-failure.md', 'window_minutes' => (int) env('ALERT_HTTP_WINDOW_MINUTES', 5)],
        'queue_backlog' => ['component' => 'queue', 'signal' => 'queue_depth', 'operator' => 'gte', 'threshold' => (int) env('ALERT_QUEUE_DEPTH', 100), 'severity' => 'warning', 'runbook' => 'docs/runbooks/queue-worker-down.md', 'window_minutes' => (int) env('ALERT_QUEUE_WINDOW_MINUTES', 10)],
        'queue_oldest_job' => ['component' => 'queue', 'signal' => 'queue_oldest_pending_seconds', 'operator' => 'gte', 'threshold' => (int) env('ALERT_QUEUE_OLDEST_SECONDS', 300), 'severity' => 'critical', 'runbook' => 'docs/runbooks/queue-backlog-recovery.md', 'window_minutes' => (int) env('ALERT_QUEUE_WINDOW_MINUTES', 10)],
        'failed_jobs' => ['component' => 'queue', 'signal' => 'failed_jobs', 'operator' => 'gte', 'threshold' => (int) env('ALERT_FAILED_JOBS', 1), 'severity' => 'critical', 'runbook' => 'docs/runbooks/queue-worker-down.md', 'window_minutes' => (int) env('ALERT_QUEUE_WINDOW_MINUTES', 10)],
        'scheduler_stale' => ['component' => 'scheduler', 'signal' => 'scheduler_age_seconds', 'operator' => 'gte', 'threshold' => (int) env('SCHEDULER_STALE_SECONDS', 180), 'severity' => 'critical', 'runbook' => 'docs/runbooks/scheduler-stale.md', 'window_minutes' => 5],
        'integration_dead_letter' => ['component' => 'integration', 'signal' => 'integration_dead_letters', 'operator' => 'gte', 'threshold' => (int) env('ALERT_INTEGRATION_DEAD_LETTERS', 1), 'severity' => 'warning', 'runbook' => 'docs/runbooks/integration-dead-letter.md', 'window_minutes' => 15],
        'notification_failure_spike' => ['component' => 'notification', 'signal' => 'notification_failures', 'operator' => 'gte', 'threshold' => (int) env('ALERT_NOTIFICATION_FAILURES', 5), 'severity' => 'warning', 'runbook' => 'docs/runbooks/queue-worker-down.md', 'window_minutes' => 10],
        'outbox_lag' => ['component' => 'outbox', 'signal' => 'outbox_oldest_pending_seconds', 'operator' => 'gte', 'threshold' => (int) env('ALERT_OUTBOX_LAG_SECONDS', 300), 'severity' => 'warning', 'runbook' => 'docs/runbooks/queue-worker-down.md', 'window_minutes' => 10],
        'database_unavailable' => ['component' => 'database', 'signal' => 'database_ready', 'operator' => 'lte', 'threshold' => 0, 'severity' => 'critical', 'runbook' => 'docs/runbooks/database-unavailable.md', 'window_minutes' => 1],
        'redis_unavailable' => ['component' => 'redis', 'signal' => 'redis_ready', 'operator' => 'lte', 'threshold' => 0, 'severity' => 'critical', 'runbook' => 'docs/runbooks/redis-unavailable.md', 'window_minutes' => 1],
        'document_storage_failure' => ['component' => 'document-storage', 'signal' => 'document_storage_failures', 'operator' => 'gte', 'threshold' => (int) env('ALERT_DOCUMENT_STORAGE_FAILURES', 5), 'severity' => 'critical', 'runbook' => 'docs/runbooks/supabase-storage-failure.md', 'window_minutes' => 5],
        'document_storage_scanner_failure' => ['component' => 'document-storage', 'signal' => 'document_storage_scanner_failures', 'operator' => 'gte', 'threshold' => (int) env('ALERT_DOCUMENT_STORAGE_SCANNER_FAILURES', 1), 'severity' => 'critical', 'runbook' => 'docs/runbooks/supabase-storage-failure.md', 'window_minutes' => 5],
        'document_storage_cleanup_failure' => ['component' => 'document-storage', 'signal' => 'document_storage_cleanup_failures', 'operator' => 'gte', 'threshold' => (int) env('ALERT_DOCUMENT_STORAGE_CLEANUP_FAILURES', 1), 'severity' => 'warning', 'runbook' => 'docs/runbooks/supabase-storage-failure.md', 'window_minutes' => 5],
        'document_storage_signed_url_failure' => ['component' => 'document-storage', 'signal' => 'document_storage_signed_url_failures', 'operator' => 'gte', 'threshold' => (int) env('ALERT_DOCUMENT_STORAGE_SIGNED_URL_FAILURES', 3), 'severity' => 'warning', 'runbook' => 'docs/runbooks/supabase-storage-failure.md', 'window_minutes' => 5],
        'document_storage_reconciliation_anomaly' => ['component' => 'document-storage', 'signal' => 'document_storage_reconciliation_anomalies', 'operator' => 'gte', 'threshold' => (int) env('ALERT_DOCUMENT_STORAGE_RECONCILIATION_ANOMALIES', 1), 'severity' => 'warning', 'runbook' => 'docs/runbooks/supabase-storage-failure.md', 'window_minutes' => 30],
    ],
];
