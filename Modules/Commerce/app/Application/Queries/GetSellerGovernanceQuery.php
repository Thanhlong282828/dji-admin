<?php

namespace Modules\Commerce\Application\Queries;

final readonly class GetSellerGovernanceQuery
{
    public function __construct(public int $companyId) {}
}
