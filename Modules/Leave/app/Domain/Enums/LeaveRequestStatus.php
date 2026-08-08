<?php

namespace Modules\Leave\Domain\Enums;

enum LeaveRequestStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case WITHDRAWN = 'withdrawn';
}
