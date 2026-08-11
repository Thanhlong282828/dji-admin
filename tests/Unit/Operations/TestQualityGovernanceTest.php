<?php

namespace Tests\Unit\Operations;

use App\Support\TestGovernance\CriticalInvariant;
use App\Support\TestGovernance\KnownTestWorkerMatcher;
use App\Support\TestGovernance\SafeTestDatabase;
use App\Support\TestGovernance\TestQualitySnapshotService;
use InvalidArgumentException;
use Tests\TestCase;

final class TestQualityGovernanceTest extends TestCase
{
    public function test_snapshot_classifies_suites_and_maps_every_critical_invariant(): void
    {
        $snapshot = app(TestQualitySnapshotService::class)->snapshot();

        self::assertGreaterThan(100, $snapshot['phpunit_files']);
        self::assertGreaterThan(0, $snapshot['browser_files']);
        self::assertSame(13, count($snapshot['critical_invariants']));
        self::assertSame(13, $snapshot['critical_covered']);
        self::assertSame(0, $snapshot['critical_gaps']);
        self::assertGreaterThan(0, $snapshot['files']['unit']);
        self::assertGreaterThan(0, $snapshot['files']['feature']);
        self::assertGreaterThan(0, $snapshot['files']['concurrency']);
        self::assertGreaterThan(0, $snapshot['files']['architecture']);
        self::assertGreaterThan(0, $snapshot['files']['security']);
        self::assertGreaterThan(0, $snapshot['files']['browser']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $snapshot['checksum']);
        self::assertStringNotContainsString('DB_PASSWORD', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_safe_database_policy_is_narrow_and_environment_specific(): void
    {
        $guard = new SafeTestDatabase;
        self::assertTrue($guard->allows('testing', 'schema_admin_testing'));
        self::assertTrue($guard->allows('testing', 'schema_admin_testing_workflow'));
        self::assertTrue($guard->allows('testing', 'schema_admin_browser_testing'));
        self::assertFalse($guard->allows('testing', 'schema_admin'));
        self::assertFalse($guard->allows('testing', 'schema_admin_development'));
        self::assertFalse($guard->allows('production', 'schema_admin_testing'));
    }

    public function test_worker_matcher_only_reports_this_projects_known_worker(): void
    {
        $root = base_path();
        $processes = "  100 php {$root}/tests/Support/workforce_concurrency_worker.php race\n"
            ."  200 php /tmp/other/tests/Support/workforce_concurrency_worker.php race\n"
            ."  300 php artisan queue:work\n";

        self::assertSame([['pid' => 100, 'command' => basename($root).'/tests/Support/workforce_concurrency_worker.php']], (new KnownTestWorkerMatcher)->matching($processes));
    }

    public function test_critical_invariant_rejects_non_critical_risk(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CriticalInvariant('INV-X', 'Core', 'Invalid risk.', 'low', ['tests/TestCase.php'], 'UNIT');
    }
}
