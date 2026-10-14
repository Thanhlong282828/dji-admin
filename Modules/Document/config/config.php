<?php

return [
    'name' => 'Document',
    'storage_disk' => env('DOCUMENT_STORAGE_DISK', 'local'),
    'temporary_url_minutes' => (int) env('DOCUMENT_TEMPORARY_URL_MINUTES', 5),
    'malware_scanner' => config('app.env') === 'production' ? 'clamav' : 'null',
    'malware_scanner_binary' => env('DOCUMENT_CLAMAV_BINARY', 'clamscan'),
    'malware_scanner_timeout_seconds' => (int) env('DOCUMENT_CLAMAV_TIMEOUT_SECONDS', 60),
    'malware_scanner_database_directory' => env('DOCUMENT_CLAMAV_DATABASE_DIRECTORY', '/var/lib/clamav'),
    'malware_scanner_max_signature_age_hours' => (int) env('DOCUMENT_CLAMAV_MAX_SIGNATURE_AGE_HOURS', 48),
    'reconciliation' => [
        'max_items' => (int) env('DOCUMENT_RECONCILIATION_MAX_ITEMS', 500),
        'max_seconds' => (int) env('DOCUMENT_RECONCILIATION_MAX_SECONDS', 30),
        'schedule_enabled' => filter_var(env('DOCUMENT_RECONCILIATION_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'companies_per_run' => (int) env('DOCUMENT_RECONCILIATION_COMPANIES_PER_RUN', 25),
        'batch_max_seconds' => (int) env('DOCUMENT_RECONCILIATION_BATCH_MAX_SECONDS', 240),
    ],
];
