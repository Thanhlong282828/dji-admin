<?php

namespace Modules\Inventory\Application\Queries;

use Modules\Inventory\Application\Services\InventoryAdjustmentReadService;

final readonly class GetInventoryAdjustmentWorkspaceHandler
{
    public function __construct(private InventoryAdjustmentReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetInventoryAdjustmentWorkspaceQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
