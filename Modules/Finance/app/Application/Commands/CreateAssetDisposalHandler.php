<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AssetLifecycleService;

final readonly class CreateAssetDisposalHandler
{
    public function __construct(private AssetLifecycleService $service) {}

    public function handle(CreateAssetDisposalCommand $command): int
    {
        return $this->service->createDisposal($command->companyId, $command->actor, $command->data);
    }
}
