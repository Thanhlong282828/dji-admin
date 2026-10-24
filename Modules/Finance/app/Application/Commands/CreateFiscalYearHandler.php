<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FiscalYearGovernanceService;

final readonly class CreateFiscalYearHandler
{
    public function __construct(private FiscalYearGovernanceService $service) {}

    public function handle(CreateFiscalYearCommand $command): int
    {
        return $this->service->createFiscalYear($command->companyId, $command->actor, $command->data);
    }
}
