<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxDownstreamType: string
{
    case AdditionalTaxRecovery = 'additional_tax_recovery';
    case TaxRefundPayment = 'tax_refund_payment';
    case AccountingJournal = 'accounting_journal';
    case AnnualStatement = 'annual_statement';
}
