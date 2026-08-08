<?php

namespace Modules\Payroll\Domain\Enums;

enum SalaryComponentCategory: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    case EmployerContribution = 'employer_contribution';
    case Informational = 'informational';
}
