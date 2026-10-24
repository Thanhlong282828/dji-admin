<?php

namespace Modules\Maintenance\Application\Queries;

use Modules\Maintenance\Application\Services\MaintenanceReadService;

final readonly class GetMaintenanceHandler
{
    public function __construct(private MaintenanceReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetMaintenanceQuery $q): array
    {
        return $this->service->read($q->companyId, $q->orderId);
    }
}
