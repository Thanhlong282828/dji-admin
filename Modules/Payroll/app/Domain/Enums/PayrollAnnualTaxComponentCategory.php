<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxComponentCategory: string
{
    case TaxableIncome = 'taxable_income';
    case NonTaxableIncome = 'non_taxable_income';
    case TaxableBenefit = 'taxable_benefit';
    case EmployeeInsuranceDeduction = 'employee_insurance_deduction';
    case PersonalDeduction = 'personal_deduction';
    case DependentDeduction = 'dependent_deduction';
    case OtherDeduction = 'other_deduction';
    case PitWithheld = 'pit_withheld';
    case PriorTaxSettlement = 'prior_tax_settlement';
}
