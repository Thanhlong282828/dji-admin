<?php

namespace Modules\Dashboard\Application\Queries;

use Modules\Dashboard\Application\Services\EnterpriseAnalyticsReadService;

final readonly class GetEnterpriseAnalyticsHandler
{
    public function __construct(private EnterpriseAnalyticsReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetEnterpriseAnalyticsQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->dateFrom, $query->dateTo);
    }
}
