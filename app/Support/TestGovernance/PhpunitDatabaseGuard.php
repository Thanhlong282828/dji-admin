<?php

namespace App\Support\TestGovernance;

use RuntimeException;

final class PhpunitDatabaseGuard
{
    public const DATABASE = 'schema_admin_testing';

    public static function assertPreLaravel(
        string $environment,
        string $database,
        string $configCachePath,
        string $expectedConfigCachePath,
    ): void {
        if ($environment !== 'testing') {
            throw new RuntimeException('PHPUnit safety guard: APP_ENV must equal testing.');
        }

        if ($database !== self::DATABASE) {
            throw new RuntimeException('PHPUnit safety guard: DB_DATABASE must equal schema_admin_testing.');
        }

        if ($configCachePath !== $expectedConfigCachePath) {
            throw new RuntimeException('PHPUnit safety guard: APP_CONFIG_CACHE must use the isolated PHPUnit cache path.');
        }

        if (is_file($configCachePath)) {
            throw new RuntimeException('PHPUnit safety guard: stale isolated config cache must be removed before tests.');
        }
    }

    public static function assertPostLaravel(string $environment, string $configuredDatabase, string $actualDatabase): void
    {
        if ($environment !== 'testing') {
            throw new RuntimeException('PHPUnit safety guard: booted Laravel environment is not testing.');
        }

        if ($configuredDatabase !== self::DATABASE) {
            throw new RuntimeException('PHPUnit safety guard: Laravel configuration does not target schema_admin_testing.');
        }

        if ($actualDatabase !== self::DATABASE) {
            throw new RuntimeException('PHPUnit safety guard: actual PDO database is not schema_admin_testing.');
        }
    }
}
