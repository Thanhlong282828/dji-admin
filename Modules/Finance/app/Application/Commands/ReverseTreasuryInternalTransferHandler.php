<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryInternalTransferService;

final readonly class ReverseTreasuryInternalTransferHandler
{
    public function __construct(private TreasuryInternalTransferService $service) {}

    public function handle(ReverseTreasuryInternalTransferCommand $command): int
    {
        return $this->service->reverse($command->companyId, $command->originalTransferId, $command->actor, $command->data);
    }
}
