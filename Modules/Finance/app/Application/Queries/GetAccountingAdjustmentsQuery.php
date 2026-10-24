<?php

namespace Modules\Finance\Application\Queries;

final readonly class GetAccountingAdjustmentsQuery
{
    public function __construct(public int $companyId) {}
}
