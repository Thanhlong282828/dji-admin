<?php

namespace Modules\Performance\Domain\Events;

final readonly class PerformanceCycleStarted
{
    public function __construct(public int $companyId, public int $cycleId) {}
}
