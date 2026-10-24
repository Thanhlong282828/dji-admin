<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FiscalYearGovernanceService;

final readonly class CloseAccountingPeriodHandler
{
    public function __construct(private FiscalYearGovernanceService $service) {}

    public function handle(CloseAccountingPeriodCommand $command): int
    {
        return $this->service->closePeriod($command->companyId, $command->periodId, $command->actor, $command->reason);
    }
}
