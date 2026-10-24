<?php

namespace Tests\Unit\Architecture;

use App\Support\TestGovernance\BrowserDatabaseGuard;
use App\Support\TestGovernance\PhpunitDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PhpunitDatabaseGuardTest extends TestCase
{
    public function test_exact_testing_environment_database_and_isolated_cache_are_allowed(): void
    {
        $this->expectNotToPerformAssertions();
        $cache = sys_get_temp_dir().'/missing-phpunit-config-'.bin2hex(random_bytes(6)).'.php';

        PhpunitDatabaseGuard::assertPreLaravel('testing', 'schema_admin_testing', $cache, $cache);
        PhpunitDatabaseGuard::assertPostLaravel('testing', 'schema_admin_testing', 'schema_admin_testing');
    }

    #[DataProvider('unsafePreLaravelCases')]
    public function test_unsafe_pre_laravel_configuration_is_rejected(string $environment, string $database): void
    {
        $cache = sys_get_temp_dir().'/missing-phpunit-config-'.bin2hex(random_bytes(6)).'.php';

        $this->expectException(RuntimeException::class);
        PhpunitDatabaseGuard::assertPreLaravel($environment, $database, $cache, $cache);
    }

    /** @return array<string, array{string, string}> */
    public static function unsafePreLaravelCases(): array
    {
        return [
            'main database is always forbidden' => ['testing', 'schema_admin'],
            'browser database is not a PHPUnit database' => ['testing', 'schema_admin_browser_testing'],
            'local environment is forbidden' => ['local', 'schema_admin_testing'],
        ];
    }

    public function test_stale_isolated_cache_is_rejected(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'phpunit-config-');
        self::assertIsString($cache);

        try {
            $this->expectException(RuntimeException::class);
            PhpunitDatabaseGuard::assertPreLaravel('testing', 'schema_admin_testing', $cache, $cache);
        } finally {
            unlink($cache);
        }
    }

    public function test_default_cached_config_cannot_redirect_an_isolated_phpunit_cache(): void
    {
        $isolated = sys_get_temp_dir().'/missing-phpunit-config-'.bin2hex(random_bytes(6)).'.php';
        $defaultCacheContainingMainDatabase = sys_get_temp_dir().'/default-config-'.bin2hex(random_bytes(6)).'.php';
        file_put_contents($defaultCacheContainingMainDatabase, "<?php return ['database' => 'schema_admin'];");

        try {
            PhpunitDatabaseGuard::assertPreLaravel('testing', 'schema_admin_testing', $isolated, $isolated);
            self::assertFileExists($defaultCacheContainingMainDatabase);
        } finally {
            unlink($defaultCacheContainingMainDatabase);
        }
    }

    #[DataProvider('unsafePostLaravelCases')]
    public function test_wrong_booted_or_actual_database_is_rejected(string $environment, string $configured, string $actual): void
    {
        $this->expectException(RuntimeException::class);
        PhpunitDatabaseGuard::assertPostLaravel($environment, $configured, $actual);
    }

    /** @return array<string, array{string, string, string}> */
    public static function unsafePostLaravelCases(): array
    {
        return [
            'cached config points at main' => ['testing', 'schema_admin', 'schema_admin'],
            'PDO points at main' => ['testing', 'schema_admin_testing', 'schema_admin'],
            'wrong environment after boot' => ['local', 'schema_admin_testing', 'schema_admin_testing'],
        ];
    }

    public function test_browser_database_has_a_separate_exact_allowlist(): void
    {
        BrowserDatabaseGuard::assertRaw('testing', 'schema_admin_browser_testing');
        BrowserDatabaseGuard::assertActual('testing', 'schema_admin_browser_testing', 'schema_admin_browser_testing');

        $this->expectException(RuntimeException::class);
        BrowserDatabaseGuard::assertRaw('testing', 'schema_admin_testing');
    }
}
