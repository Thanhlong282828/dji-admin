<?php

namespace Modules\Finance\Application\Queries;

final readonly class GetCostCenterCostManagementQuery
{
    public function __construct(public int $companyId, public ?int $costCenterId, public string $dateFrom, public string $dateTo) {}
}
