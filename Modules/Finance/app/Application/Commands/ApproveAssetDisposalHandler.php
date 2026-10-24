<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AssetLifecycleService;

final readonly class ApproveAssetDisposalHandler
{
    public function __construct(private AssetLifecycleService $service) {}

    public function handle(ApproveAssetDisposalCommand $command): int
    {
        return $this->service->approveDisposal($command->companyId, $command->id, $command->actor);
    }
}
