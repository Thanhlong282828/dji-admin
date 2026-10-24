<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AssetLifecycleService;

final readonly class PostAssetImpairmentHandler
{
    public function __construct(private AssetLifecycleService $service) {}

    public function handle(PostAssetImpairmentCommand $command): int
    {
        return $this->service->postImpairment($command->companyId, $command->id, $command->actor);
    }
}
