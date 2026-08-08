<?php

namespace Modules\Payroll\Domain\Enums;

enum TaxStatus: string
{
    case Taxable = 'taxable';
    case Exempt = 'exempt';
    case NotSubject = 'not_subject';
    case Suspended = 'suspended';
}
