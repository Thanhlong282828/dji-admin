<?php

return [
    'manifest_directory' => storage_path('release-manifests'),
    'required_quality_gates' => ['phpstan', 'phpunit', 'pint', 'blade', 'vite', 'composer_audit', 'npm_audit', 'secret_scan', 'docker'],
    'production_database' => 'schema_admin',
    'development_database_suffix' => '_development',
    'testing_database_pattern' => '/_testing(?:_[a-z0-9_]+)?$/',
    'feature_flags' => [
        'release_dashboard' => (bool) env('FEATURE_RELEASE_DASHBOARD', true),
    ],
];
