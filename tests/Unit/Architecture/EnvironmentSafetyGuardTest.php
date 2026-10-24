<?php

namespace Tests\Unit\Architecture;

use App\Support\EnvironmentSafetyGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvironmentSafetyGuardTest extends TestCase
{
    /** @return iterable<string, array{string, bool, string}> */
    public static function unsafeConfigurations(): iterable
    {
        yield 'testing cannot use production database' => ['testing', false, 'schema_admin'];
        yield 'production debug is forbidden' => ['production', true, 'vps_admin_live'];
        yield 'production testing database is forbidden' => ['production', false, 'schema_admin_testing'];
        yield 'testing database requires suffix' => ['testing', false, 'schema_admin_test'];
        yield 'staging cannot use production database' => ['staging', false, 'schema_admin'];
        yield 'staging debug is forbidden' => ['staging', true, 'laraveldji_staging_restore'];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_it_rejects_unsafe_environment_database_combinations(string $environment, bool $debug, string $database): void
    {
        $this->expectException(RuntimeException::class);
        EnvironmentSafetyGuard::assertSafe($environment, $debug, $database, [], true, 'schema_admin', 'vps_admin_live');
    }

    public function test_it_accepts_local_main_vps_production_and_isolated_testing_databases(): void
    {
        EnvironmentSafetyGuard::assertSafe('production', false, 'vps_admin_live', [], true, 'schema_admin', 'vps_admin_live');
        EnvironmentSafetyGuard::assertSafe('production', false, 'schema_admin', [], true, 'schema_admin', 'schema_admin');
        EnvironmentSafetyGuard::assertSafe('local', true, 'schema_admin', []);
        EnvironmentSafetyGuard::assertSafe('testing', true, 'schema_admin_testing', []);
        EnvironmentSafetyGuard::assertSafe('testing', true, 'schema_admin_testing_workflow', []);
        EnvironmentSafetyGuard::assertSafe('staging', false, 'laraveldji_staging_restore', []);

        $this->addToAssertionCount(6);
    }

    public function test_production_requires_an_explicit_matching_database_allowlist(): void
    {
        foreach ([
            ['', 'schema_admin'],
            ['vps_admin_live', 'schema_admin'],
        ] as [$configured, $actual]) {
            try {
                EnvironmentSafetyGuard::assertSafe('production', false, $actual, [], true, 'schema_admin', $configured);
                self::fail('An unconfigured or mismatched production database must be rejected.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_local_database_name_is_configuration_driven(): void
    {
        EnvironmentSafetyGuard::assertSafe('local', true, 'local_erp', [], true, 'local_erp');

        $this->addToAssertionCount(1);
    }
}
