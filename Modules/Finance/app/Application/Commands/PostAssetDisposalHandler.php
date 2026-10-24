<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AssetLifecycleService;

final readonly class PostAssetDisposalHandler
{
    public function __construct(private AssetLifecycleService $service) {}

    public function handle(PostAssetDisposalCommand $command): int
    {
        return $this->service->postDisposal($command->companyId, $command->id, $command->actor);
    }
}
