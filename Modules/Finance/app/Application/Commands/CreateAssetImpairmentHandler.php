<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\AssetLifecycleService;

final readonly class CreateAssetImpairmentHandler
{
    public function __construct(private AssetLifecycleService $service) {}

    public function handle(CreateAssetImpairmentCommand $command): int
    {
        return $this->service->createImpairment($command->companyId, $command->actor, $command->data);
    }
}
