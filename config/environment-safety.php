<?php

return [
    'protected_databases' => array_values(array_filter(array_map(
        static fn (string $name): string => trim($name),
        explode(',', (string) env('PROTECTED_DATABASE_NAMES', '')),
    ))),
    'local_database' => env('LOCAL_MAIN_DATABASE', 'schema_admin'),
    'require_testing_suffix' => (bool) env('REQUIRE_TESTING_DATABASE_SUFFIX', true),
];
