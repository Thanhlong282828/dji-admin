<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\AssetLifecycleReadService;

final readonly class GetAssetLifecycleHandler
{
    public function __construct(private AssetLifecycleReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetAssetLifecycleQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
