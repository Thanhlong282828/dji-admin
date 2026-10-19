<?php

namespace Modules\Procurement\Application\Queries;

use Modules\Procurement\Application\Services\ProcurementProcessHelpReadService;

final readonly class GetProcurementProcessHelpHandler
{
    public function __construct(private ProcurementProcessHelpReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetProcurementProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
