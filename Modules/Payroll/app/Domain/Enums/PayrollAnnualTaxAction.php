<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxAction: string
{
    case Calculate = 'calculate';
    case Validate = 'validate';
    case Submit = 'submit';
    case Review = 'review';
    case Approve = 'approve';
    case Finalize = 'finalize';
    case Reject = 'reject';
    case Supersede = 'supersede';
}
