<?php

namespace App\Support\DataLifecycle;

use App\Support\DataProtection\DataClassification;
use DateTimeImmutable;

final readonly class RetentionPolicy
{
    public function __construct(public string $key, public int $version, public string $owner, public string $resourceType, public DataClassification $classification, public ?int $retentionDays, public string $archiveBehavior, public string $disposalBehavior, public bool $legalHoldEligible, public string $source, public bool $enabled, public DateTimeImmutable $effectiveFrom, public ?DateTimeImmutable $effectiveTo) {}

    /** @return array<string,bool|int|string|null> */
    public function evidence(): array
    {
        return ['key' => $this->key, 'version' => $this->version, 'owner' => $this->owner, 'resource_type' => $this->resourceType, 'classification' => $this->classification->value, 'retention_days' => $this->retentionDays, 'archive_behavior' => $this->archiveBehavior, 'disposal_behavior' => $this->disposalBehavior, 'legal_hold_eligible' => $this->legalHoldEligible, 'source' => $this->source, 'enabled' => $this->enabled, 'effective_from' => $this->effectiveFrom->format('Y-m-d'), 'effective_to' => $this->effectiveTo?->format('Y-m-d')];
    }
}
