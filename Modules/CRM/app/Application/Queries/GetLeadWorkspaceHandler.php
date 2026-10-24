<?php

namespace Modules\CRM\Application\Queries;

use Modules\CRM\Application\Services\CrmLeadReadService;

final readonly class GetLeadWorkspaceHandler
{
    public function __construct(private CrmLeadReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetLeadWorkspaceQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor, $query->search, $query->status);
    }
}
