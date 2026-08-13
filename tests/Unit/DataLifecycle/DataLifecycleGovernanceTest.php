<?php

namespace Tests\Unit\DataLifecycle;

use App\Support\DataLifecycle\ArchiveArtifactMetadata;
use App\Support\DataLifecycle\LegalHold;
use App\Support\DataLifecycle\LifecycleResource;
use App\Support\DataLifecycle\LifecycleState;
use App\Support\DataLifecycle\RetentionDecision;
use App\Support\DataLifecycle\RetentionDecisionEngine;
use App\Support\DataLifecycle\RetentionPolicy;
use App\Support\DataLifecycle\RetentionPolicyRegistry;
use App\Support\DataLifecycle\RetentionPolicyResolver;
use App\Support\DataProtection\DataClassification;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

final class DataLifecycleGovernanceTest extends TestCase
{
    public function test_policy_registry_is_deterministic_classified_and_has_no_fake_legal_period(): void
    {
        $registry = new RetentionPolicyRegistry;
        self::assertCount(13, $registry->policies());
        self::assertSame($registry->checksum(), $registry->checksum());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $registry->checksum());
        self::assertSame([], array_values(array_filter($registry->policies(), static fn (RetentionPolicy $policy): bool => $policy->retentionDays !== null)));
        self::assertContains(DataClassification::Restricted, array_map(static fn (RetentionPolicy $policy): DataClassification => $policy->classification, $registry->policies()));
    }

    public function test_resolver_selects_latest_applicable_effective_version(): void
    {
        $old = $this->policy(version: 1, effectiveFrom: '2026-01-01', effectiveTo: '2026-06-30');
        $new = $this->policy(version: 2, effectiveFrom: '2026-07-01');
        $resolver = new RetentionPolicyResolver;
        self::assertSame(1, $resolver->resolve([$new, $old], 'synthetic', new DateTimeImmutable('2026-06-01'))?->version);
        self::assertSame(2, $resolver->resolve([$old, $new], 'synthetic', new DateTimeImmutable('2026-08-01'))?->version);
    }

    public function test_legal_hold_has_precedence_over_elapsed_retention_and_disposal(): void
    {
        $decision = (new RetentionDecisionEngine)->decide(
            $this->resource('2020-01-01'),
            $this->policy(retentionDays: 30),
            new LegalHold('HOLD-SYNTHETIC', 'resource', 'synthetic', 'litigation', new DateTimeImmutable('2025-01-01'), null, 'active', 'AUTH-SYNTHETIC'),
            new DateTimeImmutable('2026-08-01'),
        );
        self::assertSame(RetentionDecision::Hold, $decision->decision);
        self::assertStringContainsString('overrides', $decision->reason);
    }

    public function test_decision_engine_blocks_active_dependency_and_unapproved_policy_but_identifies_safe_dry_run_candidate(): void
    {
        $engine = new RetentionDecisionEngine;
        $at = new DateTimeImmutable('2026-08-01');
        self::assertSame(RetentionDecision::KeepActive, $engine->decide($this->resource('2020-01-01', LifecycleState::Active), $this->policy(retentionDays: 30), null, $at)->decision);
        self::assertSame(RetentionDecision::Blocked, $engine->decide($this->resource('2020-01-01', activeDependency: true), $this->policy(retentionDays: 30), null, $at)->decision);
        self::assertSame(RetentionDecision::Blocked, $engine->decide($this->resource('2020-01-01'), $this->policy(), null, $at)->decision);
        self::assertSame(RetentionDecision::DisposalEligible, $engine->decide($this->resource('2020-01-01'), $this->policy(retentionDays: 30), null, $at)->decision);
    }

    public function test_archive_integrity_metadata_requires_sha256_size_and_content_type(): void
    {
        $artifact = new ArchiveArtifactMetadata('ARCHIVE-SYNTHETIC', 'policy.synthetic', 'synthetic-range', str_repeat('a', 64), 128, 'application/json', new DateTimeImmutable('2026-08-01'));
        self::assertSame(128, $artifact->evidence()['size']);
        self::assertArrayNotHasKey('storage_path', $artifact->evidence());

        $this->expectException(InvalidArgumentException::class);
        new ArchiveArtifactMetadata('BAD', 'policy.synthetic', 'range', 'invalid', -1, 'json', new DateTimeImmutable('2026-08-01'));
    }

    public function test_cross_domain_policies_preserve_employee_integration_and_audit_ownership(): void
    {
        $policies = new RetentionPolicyRegistry;
        $byKey = [];
        foreach ($policies->policies() as $policy) {
            $byKey[$policy->key] = $policy;
        }

        self::assertSame('blocked_pending_authority', $byKey['employee.records']->disposalBehavior);
        self::assertSame('preserve_dedupe_checkpoint', $byKey['integration.delivery']->archiveBehavior);
        self::assertSame('append_only_no_application_purge', $byKey['audit.events']->disposalBehavior);
        $employee = new LifecycleResource('employee_record', new DateTimeImmutable('2020-01-01'), LifecycleState::Active, false, false, true);
        self::assertSame(RetentionDecision::KeepActive, (new RetentionDecisionEngine)->decide($employee, $byKey['employee.records'], null, new DateTimeImmutable('2026-08-01'))->decision);
    }

    private function policy(?int $retentionDays = null, int $version = 1, string $effectiveFrom = '2026-01-01', ?string $effectiveTo = null): RetentionPolicy
    {
        return new RetentionPolicy('policy.synthetic', $version, 'synthetic-owner', 'synthetic', DataClassification::Internal, $retentionDays, 'none', 'owned-adapter-only', true, 'synthetic-test-policy', true, new DateTimeImmutable($effectiveFrom), $effectiveTo === null ? null : new DateTimeImmutable($effectiveTo));
    }

    private function resource(string $date, LifecycleState $state = LifecycleState::Inactive, bool $activeDependency = false): LifecycleResource
    {
        return new LifecycleResource('synthetic', new DateTimeImmutable($date), $state, $activeDependency, false, true);
    }
}
