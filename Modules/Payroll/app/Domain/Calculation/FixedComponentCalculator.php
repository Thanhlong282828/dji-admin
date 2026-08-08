<?php

namespace Modules\Payroll\Domain\Calculation;

use DomainException;

final class FixedComponentCalculator implements PayrollComponentCalculatorInterface
{
    public function calculate(PayrollComponentInput $input, int $grossBaseMinor): int
    {
        return $input->amountMinor ?? throw new DomainException("Thiếu fixed amount cho {$input->code}.");
    }
}
