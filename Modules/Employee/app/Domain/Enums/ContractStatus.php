<?php

namespace Modules\Employee\Domain\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Approval = 'approval';
    case Approved = 'approved';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';
}
