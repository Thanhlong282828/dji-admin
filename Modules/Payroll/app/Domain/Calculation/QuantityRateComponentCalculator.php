<?php

namespace Modules\Payroll\Domain\Calculation;

use DomainException;

final class QuantityRateComponentCalculator implements PayrollComponentCalculatorInterface
{
    public function calculate(PayrollComponentInput $input, int $grossBaseMinor): int
    {
        $q = $input->quantityBasisPoints ?? throw new DomainException("Thiếu quantity cho {$input->code}.");
        $r = $input->rateMinor ?? throw new DomainException("Thiếu rate cho {$input->code}.");
        if ($q < 0 || $r < 0) {
            throw new DomainException('Quantity/rate không được âm.');
        }

        return intdiv($q * $r + 5000, 10000);
    }
}
