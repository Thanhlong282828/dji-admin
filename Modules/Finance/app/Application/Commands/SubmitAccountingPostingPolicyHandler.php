<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AccountingPostingPolicyService;

final readonly class SubmitAccountingPostingPolicyHandler
{
    public function __construct(private AccountingPostingPolicyService $service) {}

    public function handle(SubmitAccountingPostingPolicyCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->policyId, $command->actor);
    }
}
