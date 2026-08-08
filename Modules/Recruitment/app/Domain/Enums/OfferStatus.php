<?php

namespace Modules\Recruitment\Domain\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Approval = 'approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';
}
