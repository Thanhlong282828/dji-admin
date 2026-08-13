<?php

namespace App\Support\DataLifecycle;

use DateTimeImmutable;

final readonly class LegalHold
{
    public function __construct(public string $reference, public string $scope, public string $resourceType, public string $reasonCategory, public DateTimeImmutable $startedAt, public ?DateTimeImmutable $releasedAt, public string $status, public string $authorityReference) {}

    public function activeAt(DateTimeImmutable $at): bool
    {
        return $this->status === 'active' && $this->startedAt <= $at && ($this->releasedAt === null || $this->releasedAt > $at);
    }
}
