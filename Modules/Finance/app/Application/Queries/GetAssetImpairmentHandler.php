<?php

namespace Modules\Finance\Application\Queries;

use Illuminate\Support\Collection;
use Modules\Finance\Application\Services\AssetLifecycleReadService;

final readonly class GetAssetImpairmentHandler
{
    public function __construct(private AssetLifecycleReadService $service) {}

    /** @return Collection<int,object> */
    public function handle(GetAssetImpairmentQuery $query): Collection
    {
        $rows = $this->service->read($query->companyId, $query->branchId, $query->actor)['impairments'] ?? null;

        return $rows instanceof Collection ? $rows : throw new \RuntimeException('Invalid asset impairment read model.');
    }
}
