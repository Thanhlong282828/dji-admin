<?php

namespace Modules\Performance\Domain\Events;

final readonly class PerformanceClosed
{
    public function __construct(public int $companyId, public int $cycleId) {}
}
