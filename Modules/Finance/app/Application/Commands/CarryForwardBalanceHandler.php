<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FiscalYearGovernanceService;

final readonly class CarryForwardBalanceHandler
{
    public function __construct(private FiscalYearGovernanceService $service) {}

    public function handle(CarryForwardBalanceCommand $command): int
    {
        return $this->service->carryForward($command->companyId, $command->closeRequestId, $command->targetFiscalYearId, $command->actor);
    }
}
