<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FinanceOverviewReadService;

final readonly class GetFinanceOverviewHandler
{
    public function __construct(private FinanceOverviewReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetFinanceOverviewQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->dateFrom, $query->dateTo);
    }
}
