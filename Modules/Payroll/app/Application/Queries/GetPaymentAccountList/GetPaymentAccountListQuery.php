<?php

namespace Modules\Payroll\Application\Queries\GetPaymentAccountList;

final readonly class GetPaymentAccountListQuery
{
    public function __construct(public int $companyId, public ?int $employeeId = null) {}
}
