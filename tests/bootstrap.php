<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Support\TestGovernance\PhpunitDatabaseGuard;

$root = dirname(__DIR__);
$expectedCache = $root.'/bootstrap/cache/phpunit-config.php';
$configuredCache = getenv('APP_CONFIG_CACHE');
$configuredCache = is_string($configuredCache) ? $configuredCache : '';
$configuredCache = str_starts_with($configuredCache, DIRECTORY_SEPARATOR)
    ? $configuredCache
    : $root.DIRECTORY_SEPARATOR.$configuredCache;

PhpunitDatabaseGuard::assertPreLaravel(
    (string) getenv('APP_ENV'),
    (string) getenv('DB_DATABASE'),
    $configuredCache,
    $expectedCache,
);
