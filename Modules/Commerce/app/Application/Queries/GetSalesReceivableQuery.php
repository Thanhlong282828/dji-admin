<?php

namespace Modules\Commerce\Application\Queries;

final readonly class GetSalesReceivableQuery
{
    public function __construct(public int $companyId) {}
}
