<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryInternalTransferService;

final readonly class ApproveTreasuryInternalTransferHandler
{
    public function __construct(private TreasuryInternalTransferService $service) {}

    public function handle(ApproveTreasuryInternalTransferCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->transferId, $command->actor);
    }
}
