<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxResultType: string
{
    case Payable = 'payable';
    case Refund = 'refund';
    case Balanced = 'balanced';
    case Blocked = 'blocked';
}
