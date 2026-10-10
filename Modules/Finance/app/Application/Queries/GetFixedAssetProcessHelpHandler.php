<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FixedAssetProcessHelpReadService;

final readonly class GetFixedAssetProcessHelpHandler
{
    public function __construct(private FixedAssetProcessHelpReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetFixedAssetProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
