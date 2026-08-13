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

        $localDatabases = ['schema_admin', 'schema_admin_development'];
        $testingDatabase = str_ends_with($database, '_testing')
            || preg_match('/^schema_admin_testing_[a-z0-9_]+$/', $database) === 1;

        if ($environment === 'local' && ($database !== 'schema_admin' || $testingDatabase)) {
            throw new RuntimeException(sprintf('Unsafe local environment: database %s is not the configured local main database.', $database));
        }

        if ($environment === 'production' && (in_array($database, $localDatabases, true) || $testingDatabase)) {
            throw new RuntimeException(sprintf('Unsafe production environment: database %s is reserved for Mac local or automation use.', $database));
        }

        if ($environment === 'production' && $debug) {
            throw new RuntimeException('Unsafe environment: APP_DEBUG must be false in production.');
        }

        if ($environment === 'testing' && $requireTestingSuffix && ! $testingDatabase) {
            throw new RuntimeException(sprintf('Unsafe test environment: database %s must use an approved testing name.', $database));
        }
    }
}
