<?php

namespace Modules\Dashboard\Application\Queries;

use Modules\Dashboard\Application\Services\EnterpriseExecutiveDashboardReadService;

final readonly class GetEnterpriseExecutiveDashboardHandler
{
    public function __construct(private EnterpriseExecutiveDashboardReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetEnterpriseExecutiveDashboardQuery $query): array
    {
        return $this->service->read($query->companyId, $query->actorId, $query->branchId, $query->dateFrom, $query->dateTo);
    }
}
