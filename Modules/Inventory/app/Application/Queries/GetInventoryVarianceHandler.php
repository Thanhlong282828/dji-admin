<?php

namespace Modules\Inventory\Application\Queries;

use Modules\Inventory\Application\Services\InventoryAdjustmentReadService;

final readonly class GetInventoryVarianceHandler
{
    public function __construct(private InventoryAdjustmentReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetInventoryVarianceQuery $query): array
    {
        $data = $this->service->read($query->companyId, $query->branchId, $query->actor);

        return ['schema_status' => $data['schema_status'], 'metrics' => $data['metrics'], 'variances' => $data['variances']];
    }
}
