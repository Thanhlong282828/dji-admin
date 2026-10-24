<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryInternalTransferService;

final readonly class SubmitTreasuryInternalTransferHandler
{
    public function __construct(private TreasuryInternalTransferService $service) {}

    public function handle(SubmitTreasuryInternalTransferCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->transferId, $command->actor);
    }
}
