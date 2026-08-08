<?php

return [
    'name' => 'Employee',
    'malware_scanner' => env('EMPLOYEE_MALWARE_SCANNER', app()->environment('production') ? 'clamav' : 'null'),
];
