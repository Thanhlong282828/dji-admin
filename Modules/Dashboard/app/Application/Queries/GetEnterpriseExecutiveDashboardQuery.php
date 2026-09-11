<?php

namespace Modules\Dashboard\Application\Queries;

final readonly class GetEnterpriseExecutiveDashboardQuery
{
    public function __construct(public int $companyId, public int $actorId, public ?int $branchId, public string $dateFrom, public string $dateTo) {}
}
