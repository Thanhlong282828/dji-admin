<?php

namespace Modules\Employee\Domain\Enums;

enum AssignmentType: string
{
    case Primary = 'primary';
    case Concurrent = 'concurrent';
    case Temporary = 'temporary';
}
