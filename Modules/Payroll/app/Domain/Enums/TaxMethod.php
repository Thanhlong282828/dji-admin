<?php

namespace Modules\Payroll\Domain\Enums;

enum TaxMethod: string
{
    case Progressive = 'progressive';
    case Flat = 'flat';
}
