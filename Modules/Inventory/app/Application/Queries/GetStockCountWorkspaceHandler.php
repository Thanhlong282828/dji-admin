<?php

namespace Modules\Inventory\Application\Queries;

use Modules\Inventory\Application\Services\InventoryAdjustmentReadService;

final readonly class GetStockCountWorkspaceHandler
{
    public function __construct(private InventoryAdjustmentReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetStockCountWorkspaceQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
