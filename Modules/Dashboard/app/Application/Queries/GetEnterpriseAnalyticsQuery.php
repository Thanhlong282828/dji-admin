<?php

namespace Modules\Dashboard\Application\Queries;

final readonly class GetEnterpriseAnalyticsQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public string $dateFrom, public string $dateTo) {}
}
