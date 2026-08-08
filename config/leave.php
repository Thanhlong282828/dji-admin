<?php

return [
    'attachments' => [
        'disk' => env('LEAVE_ATTACHMENT_DISK', 'local'),
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
        'max_size_bytes' => (int) env('LEAVE_ATTACHMENT_MAX_SIZE', 10 * 1024 * 1024),
        'quarantine_path' => 'leave/quarantine',
        'clean_path' => 'leave/clean',
    ],
];
