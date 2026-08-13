<?php

namespace App\Support\DataLifecycle;

use DateTimeImmutable;

final readonly class LifecycleResource
{
    public function __construct(public string $type, public DateTimeImmutable $referenceDate, public LifecycleState $state, public bool $activeDependency, public bool $integrityHistoryRequired, public bool $ownedAdapterAvailable) {}
}
