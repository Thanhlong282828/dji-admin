<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FiscalYearGovernanceReadService;

final readonly class GetFiscalYearWorkspaceHandler
{
    public function __construct(private FiscalYearGovernanceReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetFiscalYearWorkspaceQuery $query): array
    {
        return $this->service->workspace($query->companyId, $query->actor);
    }
}
