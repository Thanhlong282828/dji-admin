<?php

namespace App\Support\DataLifecycle;

enum LifecycleState: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';
    case RetentionEligible = 'RETENTION_ELIGIBLE';
    case LegalHold = 'LEGAL_HOLD';
    case DisposalEligible = 'DISPOSAL_ELIGIBLE';
    case Disposed = 'DISPOSED';
}
