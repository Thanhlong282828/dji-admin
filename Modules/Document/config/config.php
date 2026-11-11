<?php

return [
    'name' => 'Document',
    'storage_disk' => config('module-runtime.document.storage_disk', 'local'),
    'temporary_url_minutes' => config('module-runtime.document.temporary_url_minutes', 5),
    'malware_scanner' => config('app.env') === 'production' ? 'clamav' : 'null',
    'malware_scanner_binary' => config('module-runtime.document.malware_scanner_binary', 'clamscan'),
    'malware_scanner_timeout_seconds' => config('module-runtime.document.malware_scanner_timeout_seconds', 60),
    'malware_scanner_database_directory' => config('module-runtime.document.malware_scanner_database_directory', '/var/lib/clamav'),
    'malware_scanner_max_signature_age_hours' => config('module-runtime.document.malware_scanner_max_signature_age_hours', 48),
    'reconciliation' => [
        'max_items' => config('module-runtime.document.reconciliation_max_items', 500),
        'max_seconds' => config('module-runtime.document.reconciliation_max_seconds', 30),
        'schedule_enabled' => config('module-runtime.document.reconciliation_schedule_enabled', false),
        'companies_per_run' => config('module-runtime.document.reconciliation_companies_per_run', 25),
        'batch_max_seconds' => config('module-runtime.document.reconciliation_batch_max_seconds', 240),
    ],
];
