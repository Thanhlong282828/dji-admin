<?php

namespace Modules\Dashboard\Application\Queries;

use Modules\Dashboard\Application\Services\SupplyChainOverviewReadService;

final readonly class GetSupplyChainOverviewHandler
{
    public function __construct(private SupplyChainOverviewReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetSupplyChainOverviewQuery $query): array
    {
        return $this->service->read($query->companyId);
    }
}
