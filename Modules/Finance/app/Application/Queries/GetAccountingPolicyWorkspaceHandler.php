<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\AccountingPolicyWorkspaceReadService;

final readonly class GetAccountingPolicyWorkspaceHandler
{
    public function __construct(private AccountingPolicyWorkspaceReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetAccountingPolicyWorkspaceQuery $query): array
    {
        return $this->service->workspace($query->companyId, $query->policyId, $query->actor);
    }
}
