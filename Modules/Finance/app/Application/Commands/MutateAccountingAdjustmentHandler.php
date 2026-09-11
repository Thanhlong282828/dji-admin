<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AccountingAdjustmentService;

final readonly class MutateAccountingAdjustmentHandler
{
    public function __construct(private AccountingAdjustmentService $service) {}

    public function handle(MutateAccountingAdjustmentCommand $command): int
    {
        return $this->service->mutate($command->action, $command->companyId, $command->actor, $command->data);
    }
}
