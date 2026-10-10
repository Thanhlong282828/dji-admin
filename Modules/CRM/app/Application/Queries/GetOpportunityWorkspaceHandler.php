<?php

namespace Modules\CRM\Application\Queries;

use Modules\CRM\Application\Services\CrmOpportunityReadService;

final readonly class GetOpportunityWorkspaceHandler
{
    public function __construct(private CrmOpportunityReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetOpportunityWorkspaceQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor, $query->search, $query->stage);
    }
}
