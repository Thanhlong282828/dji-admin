<?php

namespace Modules\Employee\Domain\Enums;

enum ConsentDecision: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
}
