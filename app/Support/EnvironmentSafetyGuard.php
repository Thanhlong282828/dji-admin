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

        if ($environment === 'testing' && $requireTestingSuffix && ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(sprintf('Unsafe test environment: database %s must end with _testing.', $database));
        }
    }
}
