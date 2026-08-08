<?php

namespace Modules\Employee\Application\Commands\RunDataQualityCheck;

final readonly class RunDataQualityCheckCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $runDate, public string $idempotencyKey) {}
}
