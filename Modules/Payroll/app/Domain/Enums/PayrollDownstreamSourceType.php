<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollDownstreamSourceType: string
{
    case RegularPayroll = 'regular_payroll';
    case RetroactiveAdjustment = 'retroactive_adjustment';
    case AdjustmentReversal = 'adjustment_reversal';
}
