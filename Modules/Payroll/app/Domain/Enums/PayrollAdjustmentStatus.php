<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAdjustmentStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Validated = 'validated';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';
    case Superseded = 'superseded';
}
