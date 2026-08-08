<?php

namespace Modules\Performance\Domain\Enums;

enum SuccessionStatus: string
{
    case Draft = 'draft';
    case Nominated = 'nominated';
    case Assessed = 'assessed';
    case Approved = 'approved';
    case Active = 'active';
    case Archived = 'archived';
}
