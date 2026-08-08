<?php

namespace Modules\Performance\Domain\Enums;

enum CareerPlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
