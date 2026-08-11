<?php

namespace App\Support\TestGovernance;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TestQualitySnapshotService
{
    /** @return array{schema_version:int,generated_at:string,checksum:string,files:array<string,int>,phpunit_files:int,browser_files:int,critical_invariants:list<array{id:string,module:string,description:string,risk:string,test_files:non-empty-list<string>,test_type:string,status:string}>,critical_covered:int,critical_gaps:int,isolation:array{database:string,concurrency_controller:string,browser_fixture:string},runtime:array{orphan_workers:int,known_flaky:int,expected_skips:int},gates:array{architecture:string,security:string,browser:string,full_regression:string},technical_debt:list<array{id:string,severity:string,description:string}>} */
    public function snapshot(): array
    {
        $files = $this->testFiles();
        $counts = ['unit' => 0, 'feature' => 0, 'integration' => 0, 'concurrency' => 0, 'security' => 0, 'architecture' => 0, 'browser' => 0, 'performance' => 0, 'operations' => 0];
        foreach ($files as $file) {
            foreach ($this->categories($file) as $category) {
                $counts[$category]++;
            }
        }
        $invariants = array_map(static fn (CriticalInvariant $invariant): array => $invariant->toArray(), $this->invariants());
        $covered = count(array_filter($invariants, static fn (array $invariant): bool => $invariant['status'] === 'covered'));
        $payload = [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'files' => $counts,
            'phpunit_files' => count(array_filter($files, static fn (string $file): bool => str_ends_with($file, 'Test.php'))),
            'browser_files' => count(array_filter($files, static fn (string $file): bool => str_ends_with($file, '.mjs'))),
            'critical_invariants' => $invariants,
            'critical_covered' => $covered,
            'critical_gaps' => count($invariants) - $covered,
            'isolation' => ['database' => 'schema_admin_testing', 'concurrency_controller' => 'serial-real-mysql', 'browser_fixture' => 'schema_admin_browser_testing'],
            'runtime' => ['orphan_workers' => $this->orphanWorkerCount(), 'known_flaky' => 0, 'expected_skips' => 1],
            'gates' => ['architecture' => 'required-early', 'security' => 'required', 'browser' => 'exact-viewport', 'full_regression' => 'required-release'],
            'technical_debt' => [
                ['id' => 'TEST-001', 'severity' => 'medium', 'description' => 'Browser scripts retain context-specific fixture and navigation setup duplication.'],
                ['id' => 'TEST-002', 'severity' => 'low', 'description' => 'Coverage tooling is informational and does not yet map executable line coverage to critical invariants.'],
                ['id' => 'TEST-003', 'severity' => 'low', 'description' => 'Several legacy module tests remain organized outside the canonical root taxonomy.'],
            ],
        ];

        return ['checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), ...$payload];
    }

    /** @return list<string> */
    private function testFiles(): array
    {
        $files = [];
        foreach ([base_path('tests'), base_path('Modules')] as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (str_ends_with($path, 'Test.php') || str_contains($path, '/tests/Browser/') && str_ends_with($path, '.mjs')) {
                    $files[] = str_replace(base_path().'/', '', $path);
                }
            }
        }
        sort($files);

        return $files;
    }

    /** @return non-empty-list<string> */
    private function categories(string $file): array
    {
        $categories = [];
        foreach (['unit', 'feature', 'integration', 'concurrency', 'security', 'architecture', 'browser', 'performance', 'operations'] as $category) {
            if (stripos($file, '/'.$category.'/') !== false || stripos(basename($file), ucfirst($category)) !== false) {
                $categories[] = $category;
            }
        }

        return $categories === [] ? ['feature'] : array_values(array_unique($categories));
    }

    /** @return list<CriticalInvariant> */
    private function invariants(): array
    {
        return [
            new CriticalInvariant('INV-ENV-001', 'Platform', 'Unsafe non-production connection to schema_admin is blocked.', 'critical', ['tests/Unit/Architecture/EnvironmentSafetyGuardTest.php'], 'SECURITY'),
            new CriticalInvariant('INV-EMP-001', 'Employee', 'Concurrent assignment allocation cannot create overlapping active allocations.', 'critical', ['tests/Concurrency/Workforce/CriticalWorkflowProcessConcurrencyTest.php'], 'CONCURRENCY'),
            new CriticalInvariant('INV-PAY-001', 'Payroll', 'Payroll approval/finalization preserves maker-checker and idempotency.', 'critical', ['tests/Concurrency/Payroll/PayrollProcessConcurrencyTest.php'], 'CONCURRENCY'),
            new CriticalInvariant('INV-WFL-001', 'Workflow', 'Workflow transitions have one winner and immutable history/outbox.', 'critical', ['tests/Concurrency/Workflow/EnterpriseWorkflowProcessConcurrencyTest.php'], 'CONCURRENCY'),
            new CriticalInvariant('INV-NOT-001', 'Notification', 'Notification logical/channel delivery remains idempotent.', 'high', ['tests/Concurrency/Notification/EnterpriseNotificationProcessConcurrencyTest.php'], 'CONCURRENCY'),
            new CriticalInvariant('INV-IAM-001', 'IAM', 'Privileged access enforces separation and process-safe provisioning.', 'critical', ['tests/Concurrency/Iam/EnterpriseIamProcessConcurrencyTest.php'], 'SECURITY/CONCURRENCY'),
            new CriticalInvariant('INV-INT-001', 'Integration', 'Outbound/webhook/dead-letter processing is idempotent.', 'critical', ['tests/Concurrency/Integration/IntegrationHubProcessConcurrencyTest.php'], 'CONCURRENCY'),
            new CriticalInvariant('INV-AUD-001', 'Audit', 'Audit events are append-only, checksummed and race-safe.', 'critical', ['tests/Concurrency/Audit/EnterpriseAuditProcessConcurrencyTest.php'], 'CONCURRENCY'),
            new CriticalInvariant('INV-TEN-001', 'Platform', 'Foreign-company resources use controlled not-found isolation.', 'critical', ['tests/Security/SecurityPolicyTest.php'], 'SECURITY'),
            new CriticalInvariant('INV-ARCH-001', 'Architecture', 'Module graph is acyclic and direct imports are declared.', 'high', ['tests/Unit/Architecture/EnterpriseArchitectureGovernanceTest.php'], 'ARCHITECTURE'),
            new CriticalInvariant('INV-NAV-001', 'Accessibility', 'Navigation is server-side permission filtered and keyboard operable.', 'high', ['tests/Feature/AccessibilityGovernanceTest.php', 'tests/Unit/Accessibility/AdminNavigationTest.php'], 'FEATURE/BROWSER'),
            new CriticalInvariant('INV-OPS-001', 'Operations', 'Redis telemetry failure cannot override the core health response.', 'critical', ['tests/Feature/OperationsReliabilityTest.php'], 'FEATURE'),
        ];
    }

    private function orphanWorkerCount(): int
    {
        $output = shell_exec('ps -axo pid=,command= 2>/dev/null');

        return count((new KnownTestWorkerMatcher)->matching(is_string($output) ? $output : ''));
    }
}
