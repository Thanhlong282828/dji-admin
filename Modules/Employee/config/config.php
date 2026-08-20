<?php

return [
    'name' => 'Employee',
    'malware_scanner' => config('app.env') === 'production' ? 'clamav' : 'null',
    'avatar_driver' => config('services.employee_avatar.driver', 'local'),
];
