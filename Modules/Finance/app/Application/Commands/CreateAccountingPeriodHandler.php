<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FiscalYearGovernanceService;

final readonly class CreateAccountingPeriodHandler
{
    public function __construct(private FiscalYearGovernanceService $service) {}

    public function handle(CreateAccountingPeriodCommand $command): int
    {
        return $this->service->createPeriod($command->companyId, $command->fiscalYearId, $command->data);
    }
}
