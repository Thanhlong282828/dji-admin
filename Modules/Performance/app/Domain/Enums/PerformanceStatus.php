<?php

namespace Modules\Performance\Domain\Enums;

enum PerformanceStatus: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case SelfReview = 'self_review';
    case ManagerReview = 'manager_review';
    case Calibration = 'calibration';
    case Approved = 'approved';
    case Closed = 'closed';
}
