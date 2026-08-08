<?php

namespace Modules\Employee\Application\Commands\RunDataQualityCheck;

use Modules\Employee\Domain\Services\HRDataGovernanceService;

final readonly class RunDataQualityCheckHandler
{
    public function __construct(private HRDataGovernanceService $service) {}

    public function handle(RunDataQualityCheckCommand $command): int
    {
        return $this->service->run($command->companyId, $command->actorId, $command->idempotencyKey, $command->runDate);
    }
}
