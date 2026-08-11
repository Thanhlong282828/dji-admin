<?php

namespace Tests\Unit\Operations;

use App\Support\Continuity\BackupInventoryService;
use App\Support\Continuity\DrCertificateGenerator;
use App\Support\Continuity\RecoveryObjectiveCalculator;
use App\Support\Continuity\ServiceContinuityCatalog;
use PHPUnit\Framework\TestCase;

final class BusinessContinuityFoundationTest extends TestCase
{
    public function test_service_criticality_and_backup_inventory_are_explicit(): void
    {
        $services = (new ServiceContinuityCatalog)->entries();
        self::assertSame('tier_0_critical', $services[0]['criticality']);
        self::assertSame('mysql', $services[0]['component']);
        $inventory = (new BackupInventoryService)->entries();
        self::assertSame('must_backup', $inventory[0]['classification']);
        self::assertContains('ephemeral', array_column($inventory, 'classification'));
    }

    public function test_recovery_objectives_distinguish_target_and_testing_observation(): void
    {
        $result = (new RecoveryObjectiveCalculator)->evaluate(60, 12.3456);
        self::assertSame(60, $result['configured_seconds']);
        self::assertSame(12.346, $result['observed_seconds']);
        self::assertTrue($result['met']);
        self::assertSame('testing_dr_exercise', $result['scope']);
    }

    public function test_certificate_is_deterministic_safe_and_fails_closed(): void
    {
        $generator = new DrCertificateGenerator;
        $arguments = ['dr-20260811', 'testing_dr_exercise', '2cacdbf', '2026-08-11T00:00:00Z', '2026-08-11T00:05:00Z', ['completed' => true, 'size_bytes' => 100], ['integrity' => true], ['redis' => true, 'mysql' => true], ['observed_seconds' => 12.5], ['observed_seconds' => 0.0], ['backup_restore' => true, 'dependency_recovery' => true]];
        $first = $generator->generate(...$arguments);
        self::assertSame($first, $generator->generate(...$arguments));
        self::assertSame('certified', $first['status']);
        self::assertSame('testing_only_not_production_certification', $first['scope']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first['checksum']);
        self::assertArrayNotHasKey('password', $first);
        $failed = $generator->generate('dr-failed', 'testing_dr_exercise', '2cacdbf', '2026-08-11T00:00:00Z', '2026-08-11T00:05:00Z', [], [], [], [], [], ['restore' => false]);
        self::assertSame('failed', $failed['status']);
    }
}
