<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollCalculationType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case QuantityRate = 'quantity_rate';
    case FormulaPlaceholder = 'formula_placeholder';
}
