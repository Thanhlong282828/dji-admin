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
        yield 'production cannot use Mac local database' => ['production', false, 'schema_admin'];
        yield 'production debug is forbidden' => ['production', true, 'vps_admin_live'];
        yield 'testing database requires suffix' => ['testing', false, 'schema_admin_test'];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_it_rejects_unsafe_environment_database_combinations(string $environment, bool $debug, string $database): void
    {
        $this->expectException(RuntimeException::class);
        EnvironmentSafetyGuard::assertSafe($environment, $debug, $database, []);
    }

    public function test_it_accepts_local_main_vps_production_and_isolated_testing_databases(): void
    {
        EnvironmentSafetyGuard::assertSafe('production', false, 'vps_admin_live', []);
        EnvironmentSafetyGuard::assertSafe('local', true, 'schema_admin', []);
        EnvironmentSafetyGuard::assertSafe('testing', true, 'schema_admin_testing', []);
        EnvironmentSafetyGuard::assertSafe('testing', true, 'schema_admin_testing_workflow', []);

        $this->addToAssertionCount(4);
    }
}
