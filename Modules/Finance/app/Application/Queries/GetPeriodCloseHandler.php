<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FiscalYearGovernanceReadService;

final readonly class GetPeriodCloseHandler
{
    public function __construct(private FiscalYearGovernanceReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetPeriodCloseQuery $query): array
    {
        return $this->service->workspace($query->companyId, $query->actor);
    }
}
