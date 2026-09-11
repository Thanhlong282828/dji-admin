<?php

namespace App\Support\TestGovernance;

use RuntimeException;

final class BrowserDatabaseGuard
{
    public const DATABASE = 'schema_admin_browser_testing';

    public static function assertRaw(string $environment, string $database): void
    {
        if ($environment !== 'testing' || $database !== self::DATABASE) {
            throw new RuntimeException('Browser safety guard requires testing + schema_admin_browser_testing.');
        }
    }

    public static function assertActual(string $environment, string $configuredDatabase, string $actualDatabase): void
    {
        self::assertRaw($environment, $configuredDatabase);

        if ($actualDatabase !== self::DATABASE) {
            throw new RuntimeException('Browser safety guard: actual PDO database is not schema_admin_browser_testing.');
        }
    }
}
