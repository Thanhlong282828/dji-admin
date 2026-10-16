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
        string $localDatabase = 'schema_admin',
        string $productionDatabase = '',
        string $stagingRestoreDatabase = 'laraveldji_staging_restore',
    ): void {
        if (in_array($database, $protectedDatabases, true) && $environment !== 'production') {
            throw new RuntimeException(sprintf('Unsafe environment: APP_ENV=%s cannot connect to protected database %s.', $environment, $database));
        }

        $testingDatabase = str_ends_with($database, '_testing')
            || preg_match('/^schema_admin_testing_[a-z0-9_]+$/', $database) === 1;

        if ($environment === 'local' && ($database !== $localDatabase || $testingDatabase)) {
            throw new RuntimeException(sprintf('Unsafe local environment: database %s is not the configured local main database.', $database));
        }

        if ($environment === 'production') {
            if ($testingDatabase) {
                throw new RuntimeException(sprintf('Unsafe production environment: testing database %s is forbidden.', $database));
            }

            if ($productionDatabase === '') {
                throw new RuntimeException('Unsafe production environment: VPS_PRODUCTION_DATABASE must be configured.');
            }

            if ($database !== $productionDatabase) {
                throw new RuntimeException(sprintf('Unsafe production environment: database %s does not match configured production database %s.', $database, $productionDatabase));
            }

            if ($debug) {
                throw new RuntimeException('Unsafe environment: APP_DEBUG must be false in production.');
            }
        }

        if ($environment === 'staging' && ($database !== $stagingRestoreDatabase || $debug)) {
            throw new RuntimeException(sprintf('Unsafe staging environment: database %s must be the canonical restore database and APP_DEBUG must be false.', $database));
        }

        if ($environment === 'testing' && $requireTestingSuffix && ! $testingDatabase) {
            throw new RuntimeException(sprintf('Unsafe test environment: database %s must use an approved testing name.', $database));
        }
    }
}
