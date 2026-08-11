<?php

namespace Tests\Unit\Architecture;

use App\Support\Architecture\ArchitectureAuditService;
use App\Support\Architecture\ArchitectureFinding;
use InvalidArgumentException;
use Tests\TestCase;

final class EnterpriseArchitectureGovernanceTest extends TestCase
{
    public function test_module_graph_is_declared_acyclic_and_has_no_untracked_direct_imports(): void
    {
        $snapshot = app(ArchitectureAuditService::class)->snapshot();

        self::assertSame(39, $snapshot['modules']);
        self::assertSame([], $snapshot['cycles']);
        self::assertSame([], $snapshot['undeclared_edges']);
        self::assertContains('Integration -> Organization', app(ArchitectureAuditService::class)->declaredEdges(app(ArchitectureAuditService::class)->manifests()));
    }

    public function test_enterprise_layers_cqrs_and_external_mutation_boundaries_are_clean(): void
    {
        $snapshot = app(ArchitectureAuditService::class)->snapshot();

        self::assertSame([], $snapshot['layer_violations']);
        self::assertSame([], $snapshot['cqrs_violations']);
        self::assertSame([], $snapshot['external_boundary_violations']);
    }

    public function test_architecture_snapshot_is_safe_typed_and_checksummed(): void
    {
        $snapshot = app(ArchitectureAuditService::class)->snapshot();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $snapshot['checksum']);
        self::assertGreaterThan(0, $snapshot['declared_edges']);
        self::assertGreaterThan(0, $snapshot['observed_edges']);
        self::assertCount(5, $snapshot['findings']);
        self::assertSame(0, $snapshot['debt']['critical']);
        self::assertSame(0, $snapshot['debt']['high']);
        self::assertStringNotContainsString('DB_PASSWORD', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_finding_rejects_unknown_severity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArchitectureFinding('ARCH-X', 'unknown', 'Core', 'LAYER', 'Invalid.', 'None.', 'None.');
    }

    public function test_required_architecture_decision_records_exist(): void
    {
        foreach (['0001-modular-monolith.md', '0002-cqrs-enterprise-boundaries.md', '0003-outbox-idempotency.md', '0004-environment-isolation.md', '0005-operations-governance.md'] as $file) {
            self::assertFileExists(base_path('docs/adr/'.$file));
        }
    }
}
