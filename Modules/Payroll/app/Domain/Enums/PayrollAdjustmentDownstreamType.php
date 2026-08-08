<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAdjustmentDownstreamType: string
{
    case Payment = 'payment';
    case Accounting = 'accounting';
    case Statement = 'statement';
    case Recovery = 'recovery';
}
