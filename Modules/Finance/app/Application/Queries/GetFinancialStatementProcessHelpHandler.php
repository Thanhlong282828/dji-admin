<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FinancialStatementProcessHelpReadService;

final readonly class GetFinancialStatementProcessHelpHandler
{
    public function __construct(private FinancialStatementProcessHelpReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetFinancialStatementProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
