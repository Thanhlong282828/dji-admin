<?php

declare(strict_types=1);

return [
    'project_id' => env('GCP_PROJECT_ID'),

    'default_version' => env(
        'GCP_SECRET_MANAGER_DEFAULT_VERSION',
        'latest'
    ),
];
