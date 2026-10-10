<?php

namespace Modules\Commerce\Application\Queries;

use Modules\Commerce\Application\Services\SalesProcessHelpReadService;

final readonly class GetSalesProcessHelpHandler
{
    public function __construct(private SalesProcessHelpReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetSalesProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
