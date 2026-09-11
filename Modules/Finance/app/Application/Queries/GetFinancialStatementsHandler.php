<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FinancialStatementReadService;

final readonly class GetFinancialStatementsHandler
{
    public function __construct(private FinancialStatementReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetFinancialStatementsQuery $query): array
    {
        return $this->service->report($query->companyId, $query->dateFrom, $query->dateTo, $query->accountId, $query->page);
    }
}
