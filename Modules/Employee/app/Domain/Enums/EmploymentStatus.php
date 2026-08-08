<?php

namespace Modules\Employee\Domain\Enums;

enum EmploymentStatus: string
{
    case Preboarding = 'preboarding';
    case Probation = 'probation';
    case Active = 'active';
    case Suspended = 'suspended';
    case Leave = 'leave';
    case Terminated = 'terminated';
    case Alumni = 'alumni';
}
