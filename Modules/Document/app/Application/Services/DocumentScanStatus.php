<?php

namespace Modules\Document\Application\Services;

enum DocumentScanStatus: string
{
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
