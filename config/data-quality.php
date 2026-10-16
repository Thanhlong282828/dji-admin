<?php

return [
    'staging_restore' => [
        'database' => env('DQ_STAGING_DATABASE', 'laraveldji_staging_restore'),
        'metadata_base_url' => 'http://metadata.google.internal/computeMetadata/v1',
    ],
];
