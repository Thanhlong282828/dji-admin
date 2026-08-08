<?php

namespace Modules\Performance\Application\Commands\RebuildAnalyticsPeriod;

final readonly class RebuildAnalyticsPeriodCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $key, public string $date) {}
}
