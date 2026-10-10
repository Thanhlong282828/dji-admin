<?php

namespace Modules\CRM\Application\Queries;

use Modules\CRM\Application\Services\CrmPipelineReadService;

final readonly class GetCrmPipelineHandler
{
    public function __construct(private CrmPipelineReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetCrmPipelineQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
