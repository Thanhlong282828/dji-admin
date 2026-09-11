<?php

namespace Modules\Dashboard\Application\Queries;

final readonly class GetSupplyChainOverviewQuery
{
    public function __construct(public int $companyId) {}
}
