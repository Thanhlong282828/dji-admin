<?php

namespace Modules\Inventory\Application\Queries;

use Modules\Inventory\Application\Services\InventoryProcessHelpReadService;

final readonly class GetInventoryProcessHelpHandler
{
    public function __construct(private InventoryProcessHelpReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetInventoryProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
