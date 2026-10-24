<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FiscalYearGovernanceService;

final readonly class OpenFiscalYearHandler
{
    public function __construct(private FiscalYearGovernanceService $service) {}

    public function handle(OpenFiscalYearCommand $command): int
    {
        return $this->service->openFiscalYear($command->companyId, $command->fiscalYearId, $command->actor);
    }
}
