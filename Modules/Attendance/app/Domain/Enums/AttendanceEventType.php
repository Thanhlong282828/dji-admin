<?php

namespace Modules\Attendance\Domain\Enums;

enum AttendanceEventType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
    case ManualIn = 'manual_in';
    case ManualOut = 'manual_out';
    case SystemAdjustment = 'system_adjustment';

    public function isIn(): bool
    {
        return in_array($this, [self::CheckIn, self::ManualIn], true);
    }

    public function isOut(): bool
    {
        return in_array($this, [self::CheckOut, self::ManualOut], true);
    }
}
