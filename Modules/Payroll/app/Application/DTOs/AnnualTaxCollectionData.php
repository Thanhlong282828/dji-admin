<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAnnualTaxIssueCode;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxValidationSeverity;

final readonly class AnnualTaxCollectionData
{
    /**
     * @param  list<AnnualTaxSourceData>  $sources
     * @param  list<array{code:PayrollAnnualTaxIssueCode,severity:PayrollAnnualTaxValidationSeverity,field:string|null,reference:string|null,message:string}>  $issues
     */
    public function __construct(public array $sources, public array $issues) {}
}
