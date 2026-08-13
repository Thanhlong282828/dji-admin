<?php

namespace App\Support\DataLifecycle;

use App\Support\DataProtection\DataClassification;
use DateTimeImmutable;
use RuntimeException;

final class RetentionPolicyRegistry
{
    /** @return list<RetentionPolicy> */
    public function policies(): array
    {
        $configured = config('data-lifecycle.policies', []);
        if (! is_array($configured)) {
            return [];
        }
        $policies = [];
        foreach ($configured as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $policies[] = $this->policy($entry);
        }

        return $policies;
    }

    public function checksum(): string
    {
        return hash('sha256', json_encode(array_map(static fn (RetentionPolicy $policy): array => $policy->evidence(), $this->policies()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<mixed> $entry */
    private function policy(array $entry): RetentionPolicy
    {
        $classification = DataClassification::tryFrom($this->string($entry, 'classification'));
        if ($classification === null) {
            throw new RuntimeException('Lifecycle policy classification is invalid.');
        }
        $effectiveTo = $entry['effective_to'] ?? null;
        $retentionDays = $entry['retention_days'] ?? null;

        return new RetentionPolicy(
            $this->string($entry, 'key'),
            $this->integer($entry, 'version'),
            $this->string($entry, 'owner'),
            $this->string($entry, 'resource_type'),
            $classification,
            is_int($retentionDays) ? $retentionDays : null,
            $this->string($entry, 'archive_behavior'),
            $this->string($entry, 'disposal_behavior'),
            ($entry['legal_hold_eligible'] ?? false) === true,
            $this->string($entry, 'source'),
            ($entry['enabled'] ?? false) === true,
            new DateTimeImmutable($this->string($entry, 'effective_from')),
            is_string($effectiveTo) ? new DateTimeImmutable($effectiveTo) : null,
        );
    }

    /** @param array<mixed> $entry */
    private function string(array $entry, string $key): string
    {
        $value = $entry[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Lifecycle policy '.$key.' is required.');
        }

        return $value;
    }

    /** @param array<mixed> $entry */
    private function integer(array $entry, string $key): int
    {
        $value = $entry[$key] ?? null;
        if (! is_int($value) || $value < 1) {
            throw new RuntimeException('Lifecycle policy '.$key.' must be a positive integer.');
        }

        return $value;
    }
}
