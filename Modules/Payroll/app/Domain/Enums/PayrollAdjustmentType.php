<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAdjustmentType: string
{
    case EarningCorrection = 'earning_correction';
    case DeductionCorrection = 'deduction_correction';
    case AttendanceCorrection = 'attendance_correction';
    case OvertimeCorrection = 'overtime_correction';
    case AllowanceCorrection = 'allowance_correction';
    case BonusCorrection = 'bonus_correction';
    case InsuranceCorrection = 'insurance_correction';
    case TaxCorrection = 'tax_correction';
    case EmploymentCorrection = 'employment_correction';
    case ManualAuthorizedAdjustment = 'manual_authorized_adjustment';
}
