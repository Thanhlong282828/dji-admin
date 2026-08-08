<?php

namespace Modules\Payroll\Domain\Calculation;

use DomainException;

final class PercentageComponentCalculator implements PayrollComponentCalculatorInterface
{
    public function calculate(PayrollComponentInput $input, int $grossBaseMinor): int
    {
        $bp = $input->percentageBasisPoints ?? throw new DomainException("Thiếu percentage cho {$input->code}.");
        if ($bp > 100000) {
            throw new DomainException('Percentage vượt giới hạn.');
        }

        return intdiv($grossBaseMinor * $bp + 5000, 10000);
    }
}
