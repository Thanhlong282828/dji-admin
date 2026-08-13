<?php

namespace App\Support\DataLifecycle;

enum RetentionDecision: string
{
    case KeepActive = 'KEEP_ACTIVE';
    case ArchiveEligible = 'ARCHIVE_ELIGIBLE';
    case Hold = 'HOLD';
    case DisposalEligible = 'DISPOSAL_ELIGIBLE';
    case Blocked = 'BLOCKED';
}
