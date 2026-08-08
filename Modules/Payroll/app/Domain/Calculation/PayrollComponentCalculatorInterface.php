<?php

namespace Modules\Payroll\Domain\Calculation;

interface PayrollComponentCalculatorInterface
{
    public function calculate(PayrollComponentInput $input, int $grossBaseMinor): int;
}
