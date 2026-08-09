<?php

namespace App\Support;

use RuntimeException;

final class EnvironmentSafetyGuard
{
    /** @param list<string> $protectedDatabases */
    public static function assertSafe(
        string $environment,
        bool $debug,
        string $database,
        array $protectedDatabases,
        bool $requireTestingSuffix = true,
    ): void {
        if (in_array($database, $protectedDatabases, true) && $environment !== 'production') {
            throw new RuntimeException(sprintf('Unsafe environment: APP_ENV=%s cannot connect to protected database %s.', $environment, $database));
        }

        if ($environment === 'production' && $debug) {
            throw new RuntimeException('Unsafe environment: APP_DEBUG must be false in production.');
        }

        $safeTestingDatabase = str_ends_with($database, '_testing')
            || preg_match('/^schema_admin_testing_[a-z0-9_]+$/', $database) === 1;
        if ($environment === 'testing' && $requireTestingSuffix && ! $safeTestingDatabase) {
            throw new RuntimeException(sprintf('Unsafe test environment: database %s must use an approved testing name.', $database));
        }
    }
}
