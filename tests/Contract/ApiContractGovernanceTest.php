<?php

namespace Tests\Contract;

use App\Support\ApiContract\ApiContractAuditService;
use Tests\TestCase;

final class ApiContractGovernanceTest extends TestCase
{
    public function test_all_governed_routes_are_documented_without_orphans_or_duplicate_ids(): void
    {
        $snapshot = app(ApiContractAuditService::class)->snapshot();
        self::assertSame(11, $snapshot['actual_paths']);
        self::assertSame(21, $snapshot['actual_route_records']);
        self::assertSame(24, $snapshot['actual_operations']);
        self::assertSame($snapshot['actual_paths'], $snapshot['documented_paths']);
        self::assertSame($snapshot['actual_operations'], $snapshot['documented_operations']);
        self::assertSame([], $snapshot['undocumented_operations']);
        self::assertSame([], $snapshot['orphan_operations']);
        self::assertSame([], $snapshot['duplicate_operation_ids']);
    }

    public function test_security_tenant_validation_pagination_and_examples_pass_governance(): void
    {
        $snapshot = app(ApiContractAuditService::class)->snapshot();
        self::assertSame('sanctum-bearer', $snapshot['security_scheme']);
        self::assertSame([], $snapshot['tenant_findings']);
        self::assertSame([], $snapshot['validation_mismatches']);
        self::assertSame([], $snapshot['response_mismatches']);
        self::assertSame([], $snapshot['pagination_findings']);
        self::assertSame([], $snapshot['safe_example_violations']);
        self::assertSame('pass', $snapshot['status']);
    }

    public function test_normalized_openapi_checksum_is_deterministic(): void
    {
        $service = app(ApiContractAuditService::class);
        self::assertSame($service->snapshot()['checksum'], $service->snapshot()['checksum']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $service->snapshot()['checksum']);
    }
}
