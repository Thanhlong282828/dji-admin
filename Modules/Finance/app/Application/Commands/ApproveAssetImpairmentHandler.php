<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AssetLifecycleService;

final readonly class ApproveAssetImpairmentHandler
{
    public function __construct(private AssetLifecycleService $service) {}

    public function handle(ApproveAssetImpairmentCommand $command): int
    {
        return $this->service->approveImpairment($command->companyId, $command->id, $command->actor);
    }
}
