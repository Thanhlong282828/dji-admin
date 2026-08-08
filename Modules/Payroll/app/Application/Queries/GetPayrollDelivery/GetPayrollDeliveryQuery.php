<?php

namespace Modules\Payroll\Application\Queries\GetPayrollDelivery;

final readonly class GetPayrollDeliveryQuery
{
    public function __construct(public int $companyId) {}
}
