<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class TaxableIncome
{
    /**
     * @param  list<array{code:string,amount_minor:int}>  $taxableComponents
     * @param  list<array{code:string,amount_minor:int}>  $nonTaxableComponents
     */
    public function __construct(public int $incomeBeforeDeductionsMinor, public int $employeeInsuranceDeductionMinor, public int $personalDeductionMinor, public int $dependentDeductionMinor, public int $otherDeductionsMinor, public int $totalDeductionsMinor, public int $beforeFloorMinor, public int $finalMinor, public string $currency, public array $taxableComponents, public array $nonTaxableComponents) {}
}
