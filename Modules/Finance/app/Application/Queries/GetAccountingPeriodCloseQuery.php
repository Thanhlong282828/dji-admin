<?php

namespace Modules\Finance\Application\Queries;

final readonly class GetAccountingPeriodCloseQuery
{
    public function __construct(public int $companyId) {}
}
