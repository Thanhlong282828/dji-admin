<?php

namespace Modules\Recruitment\Domain\Enums;

enum CandidateOfferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';
}
