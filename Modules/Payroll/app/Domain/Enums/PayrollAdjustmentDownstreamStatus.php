<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAdjustmentDownstreamStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Processing = 'processing';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Failed = 'failed';
    case NotApplicable = 'not_applicable';
}
