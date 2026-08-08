<?php

namespace Modules\Payroll\Domain\Calculation;

final readonly class PayrollCalculationResult
{
    /**
     * @param  list<PayrollCalculatedComponent>  $components
     * @param  list<string>  $issues
     */
    public function __construct(public int $grossMinor, public int $deductionMinor, public int $employerContributionMinor, public int $netMinor, public array $components, public array $issues) {}

    public function valid(): bool
    {
        return $this->issues === [];
    }
}
