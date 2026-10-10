<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FiscalYearGovernanceReadService;

final readonly class GetOpeningBalanceHandler
{
    public function __construct(private FiscalYearGovernanceReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetOpeningBalanceQuery $query): array
    {
        return $this->service->openingBalances($query->companyId, $query->actor);
    }
}
