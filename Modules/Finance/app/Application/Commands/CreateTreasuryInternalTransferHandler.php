<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryInternalTransferService;

final readonly class CreateTreasuryInternalTransferHandler
{
    public function __construct(private TreasuryInternalTransferService $service) {}

    public function handle(CreateTreasuryInternalTransferCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
