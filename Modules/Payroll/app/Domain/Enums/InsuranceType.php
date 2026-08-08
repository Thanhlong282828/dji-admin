<?php

namespace Modules\Payroll\Domain\Enums;

enum InsuranceType: string
{
    case Social = 'social';
    case Health = 'health';
    case Unemployment = 'unemployment';
    case OccupationalAccident = 'occupational_accident';
}
