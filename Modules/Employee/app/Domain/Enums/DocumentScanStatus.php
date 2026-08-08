<?php

namespace Modules\Employee\Domain\Enums;

enum DocumentScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
