<?php

namespace Modules\Payroll\Domain\Enums;

enum InsuranceParticipationStatus: string
{
    case Participating = 'participating';
    case NotParticipating = 'not_participating';
    case Exempt = 'exempt';
    case Suspended = 'suspended';
}
