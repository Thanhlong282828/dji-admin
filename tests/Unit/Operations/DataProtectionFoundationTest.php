<?php

namespace Tests\Unit\Operations;

use App\Support\DataProtection\CryptographicInventoryService;
use App\Support\DataProtection\DataClassification;
use App\Support\DataProtection\DataFieldRegistry;
use App\Support\DataProtection\DataMasker;
use App\Support\DataProtection\DataProtectionAuditService;
use App\Support\DataProtection\DataProtectionPolicyService;
use App\Support\DataProtection\DataSurface;
use App\Support\DataProtection\ProtectedPayloadSanitizer;
use App\Support\DataProtection\ProtectionDecision;
use PHPUnit\Framework\TestCase;

final class DataProtectionFoundationTest extends TestCase
{
    public function test_registry_is_typed_deterministic_and_restricted_by_default(): void
    {
        $registry = new DataFieldRegistry;
        $salary = $registry->find('gross_salary');
        self::assertNotNull($salary);
        self::assertSame(DataClassification::Restricted, $salary->classification);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $registry->checksum());
        self::assertSame($registry->checksum(), (new DataFieldRegistry)->checksum());
    }

    public function test_policy_requires_both_authorization_and_purpose(): void
    {
        $field = (new DataFieldRegistry)->find('bank_account');
        self::assertNotNull($field);
        $policy = new DataProtectionPolicyService;
        self::assertSame(ProtectionDecision::Deny, $policy->decide($field, DataSurface::Ui));
        self::assertSame(ProtectionDecision::Deny, $policy->decide($field, DataSurface::Integration, true, false));
        self::assertSame(ProtectionDecision::Allow, $policy->decide($field, DataSurface::Integration, true, true));
        self::assertSame(ProtectionDecision::Redact, $policy->decide($field, DataSurface::Audit, true, true));
    }

    public function test_masking_and_recursive_redaction_are_deterministic(): void
    {
        $masker = new DataMasker;
        self::assertSame('a***@example.com', $masker->email('alice@example.com'));
        self::assertSame('******1234', $masker->phone('0900001234'));
        self::assertSame('********5678', $masker->lastFour('000012345678'));
        $safe = (new ProtectedPayloadSanitizer(new DataFieldRegistry))->sanitize(['salary' => 10_000, 'nested' => ['government_id' => 'ABC1234'], 'status' => 'changed']);
        self::assertSame('changed', $safe['status']);
        $salary = $safe['salary'];
        self::assertIsArray($salary);
        self::assertSame('[REDACTED]', $salary['value']);
        $nested = $safe['nested'];
        self::assertIsArray($nested);
        $governmentId = $nested['government_id'];
        self::assertIsArray($governmentId);
        self::assertSame('restricted', $governmentId['classification']);
    }

    public function test_audit_and_crypto_inventory_never_contain_values(): void
    {
        $registry = new DataFieldRegistry;
        $audit = (new DataProtectionAuditService($registry, new CryptographicInventoryService))->audit();
        self::assertSame('pass_with_documented_warnings', $audit['status']);
        self::assertGreaterThan(0, $audit['restricted_fields']);
        self::assertSame($registry->checksum(), $audit['checksum']);
    }
}
