<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

final class TestEngineeringGovernanceTest extends TestCase
{
    public function test_ci_orders_readiness_and_architecture_before_serial_concurrency_and_full_regression(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
        $readiness = strpos($workflow, 'operations:test-readiness');
        $architecture = strpos($workflow, 'Early architecture and test-governance fitness gate');
        $concurrency = strpos($workflow, 'Serial process-concurrency gate');
        $full = strpos($workflow, 'php artisan test --coverage --min=20');

        self::assertIsInt($readiness);
        self::assertIsInt($architecture);
        self::assertIsInt($concurrency);
        self::assertIsInt($full);
        self::assertLessThan($architecture, $readiness);
        self::assertLessThan($concurrency, $architecture);
        self::assertLessThan($full, $concurrency);
    }

    public function test_ci_uses_only_testing_database_and_has_worker_preflight_and_postflight(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
        self::assertStringContainsString('DB_DATABASE: schema_admin_testing', $workflow);
        self::assertStringNotContainsString('DB_DATABASE: schema_admin\n', $workflow);
        self::assertDoesNotMatchRegularExpression('/^ {6}(CACHE_STORE|QUEUE_CONNECTION):/m', $workflow);
        self::assertStringContainsString('Redis permission-cache integration gate', $workflow);
        self::assertStringContainsString('CACHE_STORE: redis', $workflow);
        self::assertStringContainsString('QUEUE_CONNECTION: sync', $workflow);
        self::assertStringContainsString('REDIS_DB: 13', $workflow);
        self::assertStringContainsString('REDIS_CACHE_DB: 14', $workflow);
        self::assertStringContainsString('Full PHPUnit Coverage', $workflow);
        self::assertStringContainsString('CACHE_STORE: array', $workflow);
        self::assertStringContainsString('PHPStan Level 10 (no result cache)', $workflow);
        self::assertStringContainsString('--memory-limit=2G --debug', $workflow);
        self::assertSame(2, substr_count($workflow, 'scripts/ci/check-test-processes.sh'));
        self::assertStringContainsString('if: always()', $workflow);
    }

    public function test_test_governance_artifacts_are_read_only_and_do_not_add_a_migration(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/Operations/TestQualityController.php'));
        $route = (string) file_get_contents(base_path('routes/web.php'));
        self::assertStringNotContainsString('Process', $controller);
        self::assertStringNotContainsString('Artisan', $controller);
        self::assertStringContainsString('permission:operations.view', $route);
        $migrations = glob(database_path('migrations/*.php')) ?: [];
        self::assertNotEmpty($migrations);
        self::assertSame([], array_values(array_filter($migrations, static fn (string $file): bool => str_contains($file, 'test_quality'))));
    }
}
