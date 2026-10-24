<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AccountingPostingPolicyService;

final readonly class CreateAccountingPostingPolicyHandler
{
    public function __construct(private AccountingPostingPolicyService $service) {}

    public function handle(CreateAccountingPostingPolicyCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
