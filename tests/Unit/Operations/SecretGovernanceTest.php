<?php

namespace Tests\Unit\Operations;

use App\Support\Secrets\ConfiguredSecretProvider;
use App\Support\Secrets\CredentialRotationPlan;
use App\Support\Secrets\SecretGovernanceService;
use App\Support\Secrets\SecretReference;
use App\Support\SensitiveDataRedactor;
use InvalidArgumentException;
use Tests\TestCase;

final class SecretGovernanceTest extends TestCase
{
    public function test_reference_validation_and_provider_resolution_are_typed(): void
    {
        $provider = new ConfiguredSecretProvider;
        $reference = new SecretReference('secret://application/app-key');
        self::assertNotNull($provider->resolve($reference));
        self::assertNull($provider->resolve(new SecretReference('secret://integration/encrypted-connection')));

        $this->expectException(InvalidArgumentException::class);
        new SecretReference('plain-password');
    }

    public function test_catalog_readiness_is_deterministic_and_contains_metadata_only(): void
    {
        $service = app(SecretGovernanceService::class);
        $first = $service->readiness();
        self::assertSame($first, $service->readiness());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first['checksum']);
        $json = json_encode(array_map(static fn ($entry): array => $entry->toArray(), $service->entries()), JSON_THROW_ON_ERROR);
        $appKey = config('app.key');
        self::assertIsString($appKey);
        self::assertStringNotContainsString($appKey, $json);
        self::assertStringNotContainsString('ciphertext', $json);
    }

    public function test_rotation_lifecycle_supports_verified_cutover_and_retirement(): void
    {
        $plan = new CredentialRotationPlan;
        $plan->begin();
        self::assertSame('rotating', $plan->state());
        $plan->verifyCandidate();
        self::assertSame('rotated', $plan->state());
        $plan->retirePrevious();
        self::assertSame('retired', $plan->state());
    }

    public function test_recursive_redaction_covers_credential_key_variants(): void
    {
        $safe = (new SensitiveDataRedactor)->redact(['clientSecret' => 'TEST_ONLY_VALUE', 'nested' => ['private-key' => 'TEST_ONLY_KEY', 'access_key' => 'TEST_ONLY_ACCESS'], 'status' => 'failed']);
        self::assertSame('[REDACTED]', $safe['clientSecret']);
        self::assertIsArray($safe['nested']);
        self::assertSame('[REDACTED]', $safe['nested']['private-key']);
        self::assertSame('[REDACTED]', $safe['nested']['access_key']);
        self::assertSame('failed', $safe['status']);
    }
}
