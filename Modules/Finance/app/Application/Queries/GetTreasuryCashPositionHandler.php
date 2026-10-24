<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\TreasuryCashPositionReadService;

final readonly class GetTreasuryCashPositionHandler
{
    public function __construct(private TreasuryCashPositionReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetTreasuryCashPositionQuery $query): array
    {
        return $this->service->workspace($query->companyId, $query->asOfDate);
    }
}
