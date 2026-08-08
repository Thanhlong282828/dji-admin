<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxValidationStage: string
{
    case SourceCollection = 'source_collection';
    case Calculation = 'calculation';
    case PreSubmit = 'pre_submit';
    case PreReview = 'pre_review';
    case PreApprove = 'pre_approve';
    case PreFinalize = 'pre_finalize';
}
