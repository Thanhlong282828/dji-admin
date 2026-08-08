<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxSourceType: string
{
    case RegularPayroll = 'regular_payroll';
    case RetroactiveAdjustment = 'retroactive_adjustment';
    case TaxRefund = 'tax_refund';
    case AdditionalTaxCollection = 'additional_tax_collection';
    case ManualAuthorizedCorrection = 'manual_authorized_correction';
}
