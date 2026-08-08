<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxReconciliationStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Validated = 'validated';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Finalized = 'finalized';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
