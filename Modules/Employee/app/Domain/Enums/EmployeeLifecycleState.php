<?php

namespace Modules\Employee\Domain\Enums;

enum EmployeeLifecycleState: string
{
    case Candidate = 'candidate';
    case OfferCreated = 'offer_created';
    case Hired = 'hired';
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Probation = 'probation';
    case Confirmed = 'confirmed';
    case Leave = 'leave';
    case Terminated = 'terminated';
    case Archived = 'archived';
}
