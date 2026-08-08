<?php

namespace Modules\Employee\Domain\Enums;

enum ContractType: string
{
    case Probation = 'probation';
    case FixedTerm = 'fixed_term';
    case Indefinite = 'indefinite';
    case Contractor = 'contractor';
}
