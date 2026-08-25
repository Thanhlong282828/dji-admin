<?php

return [
    'driver' => env('MEDIA_DRIVER', env('EMPLOYEE_AVATAR_DRIVER', 'local')),
    'max_bytes' => (int) env('MEDIA_MAX_BYTES', 8 * 1024 * 1024),
    'timeout' => (int) env('CLOUDFLARE_IMAGES_TIMEOUT', 15),
];
