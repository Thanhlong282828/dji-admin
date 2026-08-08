<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class TaxCalculationResult
{
    /**
     * @param  list<TaxBracketLine>  $bracketLines
     * @param  list<string>  $issues
     */
    public function __construct(public ?int $policyId, public ?int $policyVersion, public int $taxableIncomeBeforeFloorMinor, public int $taxableIncomeMinor, public int $withholdingTaxMinor, public array $bracketLines, public array $issues, public int $employeeInsuranceDeductionMinor = 0, public int $personalDeductionMinor = 0, public int $dependentDeductionMinor = 0, public int $otherDeductionMinor = 0) {}
}
