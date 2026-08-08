<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxValidationSeverity: string
{
    case Warning = 'warning';
    case Blocking = 'blocking';
}
