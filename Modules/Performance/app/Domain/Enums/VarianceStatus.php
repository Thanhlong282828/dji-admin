<?php

namespace Modules\Performance\Domain\Enums;

enum VarianceStatus: string
{
    case OnTarget = 'ON_TARGET';
    case UnderPlan = 'UNDER_PLAN';
    case OverPlan = 'OVER_PLAN';
    case Warning = 'WARNING';
    case Blocked = 'BLOCKED';
}
