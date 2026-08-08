<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Collecting = 'collecting';
    case Review = 'review';
    case Locked = 'locked';
    case Processed = 'processed';
    case Cancelled = 'cancelled';
}
