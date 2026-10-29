<?php

return [
    'name' => 'Employee',
    'malware_scanner' => config('app.env') === 'production' ? 'clamav' : 'null',
    'malware_scanner_binary' => config('module-runtime.employee.malware_scanner_binary', 'clamscan'),
    'malware_scanner_timeout_seconds' => config('module-runtime.employee.malware_scanner_timeout_seconds', 60),
    'malware_scanner_database_directory' => config('module-runtime.employee.malware_scanner_database_directory', '/var/lib/clamav'),
    'avatar_driver' => config('services.employee_avatar.driver', 'local'),
    'document_disk' => config('module-runtime.employee.document_disk', 'local'),
    'document_temporary_url_minutes' => config('module-runtime.employee.document_temporary_url_minutes', 5),
];
