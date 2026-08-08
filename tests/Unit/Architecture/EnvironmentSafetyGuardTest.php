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
        yield 'local cannot use production database' => ['local', false, 'schema_admin'];
        yield 'testing cannot use production database' => ['testing', false, 'schema_admin'];
        yield 'production debug is forbidden' => ['production', true, 'schema_admin'];
        yield 'testing database requires suffix' => ['testing', false, 'schema_admin_test'];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_it_rejects_unsafe_environment_database_combinations(string $environment, bool $debug, string $database): void
    {
        $this->expectException(RuntimeException::class);
        EnvironmentSafetyGuard::assertSafe($environment, $debug, $database, ['schema_admin']);
    }

    public function test_it_accepts_production_and_isolated_development_or_testing_databases(): void
    {
        EnvironmentSafetyGuard::assertSafe('production', false, 'schema_admin', ['schema_admin']);
        EnvironmentSafetyGuard::assertSafe('local', true, 'schema_admin_development', ['schema_admin']);
        EnvironmentSafetyGuard::assertSafe('testing', true, 'schema_admin_testing', ['schema_admin']);

        $this->addToAssertionCount(3);
    }
}
