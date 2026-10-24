<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FiscalYearGovernanceService;

final readonly class CloseFiscalYearHandler
{
    public function __construct(private FiscalYearGovernanceService $service) {}

    public function handle(CloseFiscalYearCommand $command): int
    {
        return $this->service->closeFiscalYear($command->companyId, $command->fiscalYearId, $command->retainedEarningsAccountId, $command->idempotencyKey, $command->actor, $command->reason);
    }
}
