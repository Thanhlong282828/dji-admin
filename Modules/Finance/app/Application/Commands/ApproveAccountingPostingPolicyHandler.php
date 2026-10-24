<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AccountingPostingPolicyService;

final readonly class ApproveAccountingPostingPolicyHandler
{
    public function __construct(private AccountingPostingPolicyService $service) {}

    public function handle(ApproveAccountingPostingPolicyCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->policyId, $command->actor);
    }
}
