<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputList;

final readonly class GetPayrollInputListQuery
{
    /** @param array<string,int|string|null> $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
