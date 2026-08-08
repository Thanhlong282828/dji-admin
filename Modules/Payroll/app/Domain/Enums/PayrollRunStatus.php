<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Calculating = 'calculating';
    case Calculated = 'calculated';
    case ValidationFailed = 'validation_failed';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Finalized = 'finalized';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
