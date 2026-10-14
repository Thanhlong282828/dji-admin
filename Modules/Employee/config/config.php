<?php

return [
    'name' => 'Employee',
    'malware_scanner' => config('app.env') === 'production' ? 'clamav' : 'null',
    'malware_scanner_binary' => env('EMPLOYEE_CLAMAV_BINARY', env('DOCUMENT_CLAMAV_BINARY', 'clamscan')),
    'malware_scanner_timeout_seconds' => (int) env('EMPLOYEE_CLAMAV_TIMEOUT_SECONDS', env('DOCUMENT_CLAMAV_TIMEOUT_SECONDS', 60)),
    'malware_scanner_database_directory' => env('EMPLOYEE_CLAMAV_DATABASE_DIRECTORY', env('DOCUMENT_CLAMAV_DATABASE_DIRECTORY', '/var/lib/clamav')),
    'avatar_driver' => config('services.employee_avatar.driver', 'local'),
    'document_disk' => env('EMPLOYEE_DOCUMENT_STORAGE_DISK', 'local'),
    'document_temporary_url_minutes' => (int) env('DOCUMENT_TEMPORARY_URL_MINUTES', 5),
];
