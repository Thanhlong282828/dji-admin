<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\CostCenterCostManagementReadService;

final readonly class GetCostCenterCostManagementHandler
{
    public function __construct(private CostCenterCostManagementReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetCostCenterCostManagementQuery $query): array
    {
        return $this->service->read($query->companyId, $query->costCenterId, $query->dateFrom, $query->dateTo);
    }
}
