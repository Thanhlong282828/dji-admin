<?php

namespace Modules\Recruitment\Domain\Enums;

enum RecruitmentRequisitionStatus: string
{
    case Draft = 'draft';
    case Approval = 'approval';
    case Approved = 'approved';
    case Open = 'open';
    case Paused = 'paused';
    case Filled = 'filled';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
}
