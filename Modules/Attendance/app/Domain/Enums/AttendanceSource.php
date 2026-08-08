<?php

namespace Modules\Attendance\Domain\Enums;

enum AttendanceSource: string
{
    case WEB = 'web';
    case MOBILE = 'mobile';
    case BIOMETRIC = 'biometric';
    case FACE = 'face';
    case CARD = 'card';
    case IMPORT = 'import';
    case ADJUSTMENT = 'adjustment';
}
