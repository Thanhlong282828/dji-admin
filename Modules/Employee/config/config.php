<?php

return [
    'name' => 'Employee',
    'malware_scanner' => config('app.env') === 'production' ? 'clamav' : 'null',
];
