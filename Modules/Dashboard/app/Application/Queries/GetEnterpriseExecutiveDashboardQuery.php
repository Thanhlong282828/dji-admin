<?php

namespace Modules\Dashboard\Application\Queries;

final readonly class GetEnterpriseExecutiveDashboardQuery
{
    /**
     * @param  list<string>  $capabilities  Empty keeps the dedicated Executive workspace contract.
     */
    public function __construct(public int $companyId, public int $actorId, public ?int $branchId, public string $dateFrom, public string $dateTo, public array $capabilities = []) {}
}
