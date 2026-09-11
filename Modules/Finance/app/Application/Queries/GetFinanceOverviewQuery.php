<?php

namespace Modules\Finance\Application\Queries;

final readonly class GetFinanceOverviewQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public ?string $dateFrom, public ?string $dateTo) {}
}
