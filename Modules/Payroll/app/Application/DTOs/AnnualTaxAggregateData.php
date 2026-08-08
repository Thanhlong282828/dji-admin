<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxAggregateData
{
    public function __construct(public int $grossIncomeMinor, public int $taxableIncomeBeforeDeductionsMinor, public int $nonTaxableIncomeMinor, public int $employeeInsuranceDeductionMinor, public int $personalDeductionMinor, public int $dependentDeductionMinor, public int $otherDeductionMinor, public int $taxableIncomeAfterDeductionsMinor, public int $pitWithheldMinor, public int $priorTaxSettlementMinor, public int $sourceCount, public int $componentCount, public string $currency, public int $taxYear, public string $jurisdiction) {}
}
