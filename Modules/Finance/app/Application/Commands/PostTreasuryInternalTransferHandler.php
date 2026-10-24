<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryInternalTransferService;

final readonly class PostTreasuryInternalTransferHandler
{
    public function __construct(private TreasuryInternalTransferService $service) {}

    public function handle(PostTreasuryInternalTransferCommand $command): int
    {
        return $this->service->post($command->companyId, $command->transferId, $command->actor);
    }
}
