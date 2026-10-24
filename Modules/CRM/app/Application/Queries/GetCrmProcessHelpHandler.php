<?php

namespace Modules\CRM\Application\Queries;

use Modules\CRM\Application\Services\CrmProcessHelpReadService;

final readonly class GetCrmProcessHelpHandler
{
    public function __construct(private CrmProcessHelpReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetCrmProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor, $query->workspace);
    }
}
