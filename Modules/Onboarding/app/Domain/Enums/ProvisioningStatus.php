<?php

namespace Modules\Onboarding\Domain\Enums;

enum ProvisioningStatus: string
{
    case Pending = 'pending';
    case Requested = 'requested';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case NotApplicable = 'not_applicable';
}
