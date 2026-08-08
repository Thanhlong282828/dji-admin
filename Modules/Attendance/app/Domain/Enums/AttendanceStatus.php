<?php

namespace Modules\Attendance\Domain\Enums;

enum AttendanceStatus: string
{
    case INCOMPLETE = 'incomplete';
    case PRESENT = 'present';
    case LATE = 'late';
    case EARLY_LEAVE = 'early_leave';
    case LATE_AND_EARLY = 'late_and_early';
    case ABSENT = 'absent';
}
